        </main>

        <!-- Footer -->
        <footer class="p-6 border-t border-slate-800 text-center text-xs text-slate-500">
            &copy; <?= date('Y') ?> <strong>LOCK & ROOM (L n' R)</strong> — Panel Pengelolaan Pemilik Kos & Kontrakan.
        </footer>
    </div>

    <!-- Global Toast notification handler -->
    <script>
        <?php if ($flashSuccess = getFlash('success')): ?>
            Swal.fire({
                icon: 'success',
                title: 'Berhasil!',
                text: '<?= addslashes($flashSuccess) ?>',
                background: '#1e293b',
                color: '#fff',
                confirmButtonColor: '#4f46e5'
            });
        <?php endif; ?>

        <?php if ($flashError = getFlash('error')): ?>
            Swal.fire({
                icon: 'error',
                title: 'Oops...',
                text: '<?= addslashes($flashError) ?>',
                background: '#1e293b',
                color: '#fff',
                confirmButtonColor: '#4f46e5'
            });
        <?php endif; ?>
    </script>
</body>
</html>
