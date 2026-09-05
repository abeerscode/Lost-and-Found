<?php if (is_admin_logged_in()): ?>
        </main>
        <footer class="admin-workspace-footer">Lost &amp; Found administration · <?= date('Y') ?></footer>
    </section>
</div>
<?php else: ?>
</main>
<?php endif; ?>
<script src="<?= BASE_URL ?>/js/main.js?v=<?= @filemtime(__DIR__ . '/../js/main.js') ?: time() ?>"></script>
</body>
</html>
