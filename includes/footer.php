<?php
// includes/footer.php - Closes the page and loads the shared animation script.
$show_footer = $show_footer ?? true;
?>
    </main>

<?php if ($show_footer): ?>
    <footer class="footer">
        <div class="footer__inner">
            <p class="footer__brand">CARE Group Medical Services</p>
            <p class="footer__note">Book verified specialists across Pakistan. In an emergency, call 1122.</p>
            <p class="footer__copy">&copy; <?php echo date('Y'); ?> CARE Group. Aptech eProject.</p>
        </div>
    </footer>
<?php endif; ?>

    <script src="<?php echo h(url('assets/js/app.js')); ?>" defer></script>
</body>
</html>
