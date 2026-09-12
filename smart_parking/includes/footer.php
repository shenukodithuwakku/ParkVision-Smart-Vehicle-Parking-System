    </div><!-- /.user-body -->
  </div><!-- /.user-content -->
</div><!-- /.user-layout -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/theme.js"></script>
<script>
(function(){
  function tick(){
    var now = new Date();
    var h=now.getHours(), m=now.getMinutes(), s=now.getSeconds();
    var pad = n=>String(n).padStart(2,'0');
    var days=['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
    var months=['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    var timeStr = pad(h)+':'+pad(m)+':'+pad(s);
    var dateStr = days[now.getDay()]+', '+now.getDate()+' '+months[now.getMonth()]+' '+now.getFullYear();
    var tc=document.getElementById('userClockDisplay');
    var dc=document.getElementById('userDateDisplay');
    if(tc) tc.textContent = timeStr;
    if(dc) dc.textContent = dateStr;
  }
  tick(); setInterval(tick, 1000);
})();
</script>

<script>
function pvClock() {
  const now = new Date();
  const hms = now.toLocaleTimeString('en-US', {hour12: false, hour:'2-digit', minute:'2-digit', second:'2-digit'});
  const date = now.toLocaleDateString('en-US', {weekday:'short', day:'numeric', month:'short'});
  const uc = document.getElementById('userClockDisplay');
  if (uc) uc.textContent = hms;
  const ud = document.getElementById('userDateDisplay');
  if (ud) ud.textContent = date;
}
pvClock(); setInterval(pvClock, 1000);
</script>
</body>
</html>
