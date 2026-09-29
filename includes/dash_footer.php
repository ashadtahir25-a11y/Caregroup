<?php
// includes/dash_footer.php - Closes the dashboard layout and loads dashboard scripts.
?>
    </section>
</div>
<script src="<?php echo h(url('assets/js/dashboard.js')); ?>" defer></script>
<?php
$show_footer = false;
include __DIR__ . '/footer.php';