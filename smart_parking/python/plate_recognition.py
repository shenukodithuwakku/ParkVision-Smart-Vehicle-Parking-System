"""
plate_recognition.py
---------------------
Runs at the parking entrance. Watches a camera feed, detects the
license-plate region of any vehicle that drives up using a YOLOv8
detector, reads the text on the plate with EasyOCR, and reports the
plate number to the PHP backend's entry API so the system can decide
whether the vehicle has a reservation, issue a QR code, and open the
slot.

USAGE
    python plate_recognition.py

CONFIGURATION
    Edit the CONFIG block below before running. At minimum set
    API_BASE_URL to point at your WAMP install and DEVICE_API_KEY to
    match config/config.php's DEVICE_API_KEY.

PLATE DETECTOR MODEL
    This script expects a YOLOv8 model trained specifically to detect
    license plates, saved at python/models/license_plate_detector.pt.
    Several such models are freely available online (search "YOLOv8
    license plate detector weights" — for example on Roboflow
    Universe or Hugging Face) — download one and drop it in that
    folder. If no custom model is found, the script automatically
    falls back to OCR-ing a fixed region of the frame on a timer,
    which is enough to demo the end-to-end flow but is far less
    accurate than a real plate detector — for the actual FYP
    deployment, plug in a proper plate-detector .pt file.
"""

import os
import re
import time
from datetime import datetime

import cv2
import numpy as np
import requests

# ----------------------------------------------------------------------
# CONFIG — edit these before running
# ----------------------------------------------------------------------
API_BASE_URL = "http://localhost/smart_parking/"          # trailing slash
DEVICE_API_KEY = "CHANGE_THIS_TO_A_RANDOM_SECRET_BEFORE_DEPLOYMENT"  # must match config/config.php
CAMERA_SOURCE = 0            # 0 = default webcam; or an RTSP/HTTP URL string for an IP camera
PLATE_MODEL_PATH = os.path.join(os.path.dirname(__file__), "models", "license_plate_detector.pt")
DETECTION_CONFIDENCE = 0.45  # YOLO confidence threshold
OCR_CONFIDENCE = 0.35        # EasyOCR confidence threshold
COOLDOWN_SECONDS = 30        # don't re-report the same plate within this window
SHOW_PREVIEW_WINDOW = True   # set False to run headless on a server with no display

PLATE_REGEX = re.compile(r"[A-Z0-9]{4,12}")

# ----------------------------------------------------------------------


def normalize_plate(raw_text: str) -> str:
    """Mirror includes/functions.php's normalize_plate(): uppercase, strip spaces/dashes."""
    return re.sub(r"[\s\-]", "", raw_text).upper()


def load_plate_detector():
    """Load the custom YOLOv8 plate detector if present, else return None (fallback mode)."""
    if os.path.exists(PLATE_MODEL_PATH):
        from ultralytics import YOLO
        print(f"[INFO] Loaded plate detector model from {PLATE_MODEL_PATH}")
        return YOLO(PLATE_MODEL_PATH)
    print("[WARN] No custom plate-detector model found at:")
    print(f"       {PLATE_MODEL_PATH}")
    print("[WARN] Falling back to fixed-region OCR mode (lower accuracy).")
    print("       Download a YOLOv8 license-plate-detector .pt file and place it there")
    print("       for real plate localization.")
    return None


def detect_plate_regions(model, frame):
    """Return a list of (x1, y1, x2, y2) crops likely to contain a plate."""
    if model is not None:
        results = model.predict(frame, conf=DETECTION_CONFIDENCE, verbose=False)
        boxes = []
        for r in results:
            for box in r.boxes:
                x1, y1, x2, y2 = map(int, box.xyxy[0].tolist())
                boxes.append((x1, y1, x2, y2))
        return boxes

    # Fallback: just OCR a fixed central-lower band of the frame, where a
    # plate would typically sit if a vehicle is centered in front of the camera.
    h, w = frame.shape[:2]
    return [(int(w * 0.20), int(h * 0.55), int(w * 0.80), int(h * 0.92))]


def read_plate_text(reader, frame, box):
    x1, y1, x2, y2 = box
    x1, y1 = max(0, x1), max(0, y1)
    crop = frame[y1:y2, x1:x2]
    if crop.size == 0:
        return None, 0.0

    gray = cv2.cvtColor(crop, cv2.COLOR_BGR2GRAY)
    gray = cv2.bilateralFilter(gray, 11, 17, 17)

    results = reader.readtext(gray)
    best_text, best_conf = None, 0.0
    for _, text, conf in results:
        cleaned = normalize_plate(text)
        if PLATE_REGEX.fullmatch(cleaned) and conf > best_conf:
            best_text, best_conf = cleaned, conf
    return best_text, best_conf


def report_entry(plate_number: str, confidence: float):
    try:
        resp = requests.post(
            API_BASE_URL.rstrip("/") + "/api/entry.php",
            json={"plate_number": plate_number, "confidence": round(confidence, 3)},
            headers={"X-Device-Key": DEVICE_API_KEY},
            timeout=8,
        )
        data = resp.json()
        if resp.status_code == 200 and data.get("success"):
            print(f"[ENTRY OK] {plate_number} -> slot {data.get('slot_code')} "
                  f"(mode={data.get('mode')}, qr_token={data.get('qr_token')[:10]}...)")
        else:
            print(f"[ENTRY REJECTED] {plate_number}: {data.get('message')}")
    except requests.RequestException as exc:
        print(f"[ERROR] Could not reach backend: {exc}")


def main():
    print("Smart Parking — Entry Plate Recognition")
    print(f"API base URL : {API_BASE_URL}")
    print(f"Camera source: {CAMERA_SOURCE}")
    print("-" * 50)

    detector = load_plate_detector()

    import easyocr
    print("[INFO] Loading EasyOCR reader (first run downloads model weights)...")
    reader = easyocr.Reader(["en"], gpu=False)

    cap = cv2.VideoCapture(CAMERA_SOURCE)
    if not cap.isOpened():
        print(f"[FATAL] Could not open camera source: {CAMERA_SOURCE}")
        return

    last_seen = {}  # plate -> timestamp, for cooldown de-duplication

    try:
        while True:
            ok, frame = cap.read()
            if not ok:
                print("[WARN] Failed to read frame, retrying...")
                time.sleep(0.5)
                continue

            boxes = detect_plate_regions(detector, frame)

            for box in boxes:
                plate_text, conf = read_plate_text(reader, frame, box)

                x1, y1, x2, y2 = box
                color = (0, 200, 0) if plate_text else (0, 0, 200)
                cv2.rectangle(frame, (x1, y1), (x2, y2), color, 2)
                if plate_text:
                    cv2.putText(frame, f"{plate_text} ({conf:.2f})", (x1, max(0, y1 - 8)),
                                cv2.FONT_HERSHEY_SIMPLEX, 0.7, color, 2)

                if plate_text and conf >= OCR_CONFIDENCE:
                    now = time.time()
                    if plate_text not in last_seen or (now - last_seen[plate_text]) > COOLDOWN_SECONDS:
                        last_seen[plate_text] = now
                        print(f"[{datetime.now().strftime('%H:%M:%S')}] Detected plate: "
                              f"{plate_text} (confidence {conf:.2f})")
                        report_entry(plate_text, conf)

            if SHOW_PREVIEW_WINDOW:
                cv2.imshow("Smart Parking — Entry Camera (press Q to quit)", frame)
                if cv2.waitKey(1) & 0xFF == ord("q"):
                    break

    except KeyboardInterrupt:
        pass
    finally:
        cap.release()
        cv2.destroyAllWindows()
        print("[INFO] Camera released. Exiting.")


if __name__ == "__main__":
    main()
