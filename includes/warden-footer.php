</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/script.js"></script>
</body>
</html>
<?php
$__content = ob_get_clean();
$__content = substr($__content, strpos($__content, '<!DOCTYPE html>'));
echo $__content;
?>
