<?php /* Shared auth page CSS — included by login, register, admin login */ ?>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
html,body{height:100%;overflow:hidden;font-family:'Inter',sans-serif;}

body{
  min-height:100vh;
  background:#06090f url('<?= BASE_URL ?>assets/images/bg_login.jpg') center/cover no-repeat;
  position:relative;display:flex;align-items:center;
}
body::before{
  content:'';position:fixed;inset:0;z-index:0;
  background:linear-gradient(105deg,
    rgba(4,8,20,.38) 0%,
    rgba(4,8,20,.35) 38%,
    rgba(4,8,20,.62) 60%,
    rgba(4,8,20,.80) 100%);
}

/* ── Layout ── */
.pv-page{
  position:relative;z-index:1;
  width:100%;max-width:1440px;margin:0 auto;
  display:flex;align-items:center;
  padding:0 5% 0 6%;min-height:100vh;
}
.pv-brand{
  flex:1;min-width:0;
  display:flex;flex-direction:column;justify-content:center;
  padding-bottom:4rem;
}
.pv-logo-img{
  height:90px;width:auto;
  filter:drop-shadow(0 0 18px rgba(37,140,235,.7))
         drop-shadow(0 0 6px rgba(56,189,248,.55));
}

/* ── Card ── */
.pv-card{
  width:440px;flex-shrink:0;
  background:rgba(10,18,42,.88);
  border:1px solid rgba(56,140,235,.28);
  border-radius:22px;
  padding:2.8rem 2.6rem 2.4rem;
  margin-left:auto;position:relative;overflow:hidden;
  box-shadow:
    0 0 0 1px rgba(56,189,248,.08) inset,
    0 0 50px rgba(37,99,235,.12),
    0 40px 100px rgba(0,0,0,.75),
    0 8px 32px rgba(0,0,0,.5);
  backdrop-filter:blur(28px);-webkit-backdrop-filter:blur(28px);
}
.pv-card.wide{width:480px;}
.pv-card::before{
  content:'';position:absolute;top:16px;right:20px;
  width:88px;height:60px;
  background-image:radial-gradient(circle,rgba(56,189,248,.22) 1.2px,transparent 1.2px);
  background-size:10px 10px;pointer-events:none;
}
.pv-card::after{
  content:'';position:absolute;top:0;left:10%;right:10%;height:1px;
  background:linear-gradient(90deg,transparent,rgba(56,189,248,.55),transparent);
  pointer-events:none;
}
.card-title{
  font-size:1.9rem;font-weight:800;color:#fff;
  text-align:center;letter-spacing:-.025em;margin-bottom:.35rem;
}
.card-title em{
  font-style:normal;
  background:linear-gradient(135deg,#60a5fa,#38bdf8);
  -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;
}
.card-sub{
  font-size:.88rem;color:rgba(255,255,255,.42);
  text-align:center;margin-bottom:2rem;
}
.card-sub-sm{margin-bottom:1.5rem;}

/* ── Fields ── */
.field{
  display:flex;align-items:center;gap:.85rem;
  background:rgba(255,255,255,.055);
  border:1.5px solid rgba(255,255,255,.13);
  border-radius:14px;padding:.88rem 1.15rem;
  margin-bottom:.85rem;
  transition:border-color .2s,background .2s,box-shadow .2s;
}
.field:focus-within{
  border-color:rgba(56,189,248,.55);
  background:rgba(37,99,235,.07);
  box-shadow:0 0 0 4px rgba(37,99,235,.14);
}
.field .fi{color:rgba(255,255,255,.38);font-size:1.1rem;flex-shrink:0;}
.field input{
  flex:1;background:none;border:none;outline:none;
  color:#fff;font-size:.97rem;font-family:'Inter',sans-serif;
}
.field input::placeholder{color:rgba(255,255,255,.28);}
.field .eye{
  background:none;border:none;color:rgba(255,255,255,.3);
  cursor:pointer;padding:0;font-size:1rem;flex-shrink:0;transition:color .15s;
}
.field .eye:hover{color:rgba(255,255,255,.7);}

/* Two-col row */
.field-row{display:grid;grid-template-columns:1fr 1fr;gap:.75rem;}

/* Password strength bar */
.pw-bar-wrap{height:3px;background:rgba(255,255,255,.08);border-radius:2px;margin:-.5rem 0 .8rem;overflow:hidden;}
.pw-bar{height:100%;width:0;border-radius:2px;transition:width .3s,background .3s;}

/* ── Meta row ── */
.meta-row{display:flex;justify-content:space-between;align-items:center;margin-bottom:1.6rem;}
.chk-label{display:flex;align-items:center;gap:.55rem;cursor:pointer;}
.chk-label input[type=checkbox]{width:17px;height:17px;accent-color:#2563eb;border-radius:4px;cursor:pointer;}
.chk-label span{font-size:.88rem;color:rgba(255,255,255,.7);}
.meta-row a,.meta-link{font-size:.88rem;color:#38bdf8;text-decoration:none;font-weight:600;}
.meta-row a:hover,.meta-link:hover{color:#7dd3fc;}

/* ── Buttons ── */
.btn-login{
  width:100%;padding:.9rem 1rem;border:none;border-radius:14px;
  background:linear-gradient(135deg,#2563eb 0%,#1d4ed8 60%,#1e3a8a 100%);
  color:#fff;font-weight:700;font-size:1.05rem;font-family:'Inter',sans-serif;
  cursor:pointer;letter-spacing:.02em;
  box-shadow:0 6px 26px -4px rgba(37,99,235,.7),0 2px 8px rgba(0,0,0,.4);
  transition:transform .15s,box-shadow .15s,filter .15s;
  display:flex;align-items:center;justify-content:center;gap:.55rem;
}
.btn-login:hover{filter:brightness(1.12);transform:translateY(-1px);box-shadow:0 10px 30px -4px rgba(37,99,235,.8);}
.btn-login:active{transform:translateY(0);filter:brightness(.97);}

.btn-ghost{
  width:100%;padding:.86rem 1rem;
  border:1.5px solid rgba(56,189,248,.25);border-radius:14px;
  background:rgba(56,189,248,.05);
  color:rgba(255,255,255,.82);font-weight:600;font-size:.97rem;
  font-family:'Inter',sans-serif;cursor:pointer;
  display:flex;align-items:center;justify-content:center;gap:.6rem;
  transition:background .2s,border-color .2s;
}
.btn-ghost:hover{background:rgba(37,99,235,.14);border-color:rgba(56,189,248,.5);}
.btn-ghost i{color:#38bdf8;font-size:1rem;}

/* ── Admin badge ── */
.admin-badge{
  display:flex;align-items:center;justify-content:center;gap:.45rem;
  background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.28);
  border-radius:50rem;padding:.3rem 1rem;width:fit-content;
  margin:0 auto 1.4rem;color:#fca5a5;font-size:.74rem;font-weight:700;letter-spacing:.04em;
}

/* ── OR divider ── */
.or-row{display:flex;align-items:center;gap:.8rem;margin:1.1rem 0;}
.or-row hr{flex:1;border:none;border-top:1px solid rgba(255,255,255,.1);}
.or-row span{font-size:.8rem;color:rgba(255,255,255,.28);letter-spacing:.05em;}

/* ── Errors ── */
.auth-err{
  background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);
  border-radius:12px;padding:.7rem 1rem;margin-bottom:1rem;
  color:#fca5a5;font-size:.85rem;
}
.auth-err ul{margin:0;padding-left:1.1rem;}
.auth-ok{
  background:rgba(37,99,235,.12);border:1px solid rgba(56,189,248,.3);
  border-radius:12px;padding:.7rem 1rem;margin-bottom:1rem;
  color:#93c5fd;font-size:.85rem;
}

/* ── Footer link ── */
.card-foot{text-align:center;margin-top:1.25rem;font-size:.88rem;color:rgba(255,255,255,.38);}
.card-foot a{color:#38bdf8;font-weight:700;text-decoration:none;}
.card-foot a:hover{color:#7dd3fc;}

.page-copy{
  position:fixed;bottom:1rem;left:50%;transform:translateX(-50%);
  font-size:.72rem;color:rgba(255,255,255,.2);white-space:nowrap;z-index:2;
}
@media(max-width:860px){
  html,body{overflow:auto;}
  .pv-brand{display:none;}
  .pv-page{padding:2rem 1.25rem;justify-content:center;}
  .pv-card,.pv-card.wide{width:100%;max-width:440px;margin:0 auto;}
}
</style>
