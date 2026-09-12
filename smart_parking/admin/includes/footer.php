    </div><!-- admin-page-body -->
  </div><!-- admin-content -->
</div><!-- admin-wrapper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/theme.js"></script>

<script>
/* Live clock for admin + user dashboards */
function pvClock() {
  const now = new Date();
  const hms = now.toLocaleTimeString('en-US', {hour12: false, hour:'2-digit', minute:'2-digit', second:'2-digit'});
  const date = now.toLocaleDateString('en-US', {weekday:'short', day:'numeric', month:'short'});
  
  const ac = document.getElementById('adminClockDisplay');
  if (ac) ac.textContent = hms;

  const uc = document.getElementById('userClockDisplay');
  if (uc) uc.textContent = hms;
  
  const ud = document.getElementById('userDateDisplay');
  if (ud) ud.textContent = date;
}
pvClock();
setInterval(pvClock, 1000);
</script>
</body>
</html>
