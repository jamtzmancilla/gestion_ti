<footer class="app-footer mt-auto py-2 text-center text-muted small">
  <?= APP_NAME ?> v<?= APP_VERSION ?> &mdash; <?= date('Y') ?>
</footer>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- App JS -->
<script src="assets/js/app.js"></script>

<?php if (!empty($flash)): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const map = {
    success : { icon: 'success', color: '#198754' },
    danger  : { icon: 'error',   color: '#dc3545' },
    warning : { icon: 'warning', color: '#ffc107' },
    info    : { icon: 'info',    color: '#0dcaf0' },
  };
  const t = map['<?= h($flash['type']) ?>'] ?? { icon: 'info', color: '#0dcaf0' };
  Swal.fire({
    toast            : true,
    position         : 'top-end',
    icon             : t.icon,
    title            : <?= json_encode($flash['msg']) ?>,
    showConfirmButton: false,
    timer            : 3800,
    timerProgressBar : true,
    customClass      : { popup: 'swal-toast-ti' },
    didOpen: (toast) => {
      toast.addEventListener('mouseenter', Swal.stopTimer);
      toast.addEventListener('mouseleave', Swal.resumeTimer);
    }
  });
});
</script>
<?php endif; ?>

</body>
</html>
