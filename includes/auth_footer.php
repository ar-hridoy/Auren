<?php
/**
 * includes/auth_footer.php
 *
 * Closes the split-screen auth layout opened by auth_header.php. Renders
 * the colour panel on the right if the page asked for it, then closes the
 * document. Bootstrap JS is included for consistency though the auth pages
 * are mostly static.
 */
?>
        </div><!-- /.auren-auth-form-inner -->
    </div><!-- /.auren-auth-form-col -->
    <?php if (($panelSide ?? 'left') === 'right') { echo $panelHtml; } ?>
</div><!-- /.auren-auth-split -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
