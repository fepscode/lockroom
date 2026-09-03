        </main>

        <!-- Footer Desktop -->
        <footer class="p-6 border-t border-slate-200 dark:border-slate-800 text-center text-xs text-slate-500 hidden md:block">
            <div>&copy; <?= date('Y') ?> <strong>LOCK & ROOM (L n' R)</strong> — Panel Pengelolaan Pemilik Kos & Kontrakan.</div>
            <div class="mt-1 text-[11px]">
                Powered by <a href="https://fepscode.my.id" target="_blank" rel="noopener noreferrer" class="font-extrabold text-indigo-600 dark:text-cyan-400 hover:underline">FeCo</a>
            </div>
        </footer>
    </div>

    <!-- Mobile Bottom Navigation Bar (Fixed for Phones) -->
    <nav class="md:hidden fixed bottom-0 left-0 right-0 z-30 bg-white/90 dark:bg-slate-900/90 backdrop-blur-lg border-t border-slate-200 dark:border-slate-800 px-2 py-2 shadow-lg">
        <div class="grid grid-cols-5 gap-1 items-center">
            <a href="index.php" class="flex flex-col items-center justify-center py-1 rounded-xl transition-all <?= ($currentPage ?? '') === 'index.php' ? 'text-indigo-600 dark:text-indigo-400 font-bold' : 'text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' ?>">
                <i class="fa-solid fa-gauge-high text-lg"></i>
                <span class="text-[10px] mt-0.5">Dashboard</span>
            </a>
            
            <a href="rooms.php" class="flex flex-col items-center justify-center py-1 rounded-xl transition-all <?= ($currentPage ?? '') === 'rooms.php' ? 'text-indigo-600 dark:text-indigo-400 font-bold' : 'text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' ?>">
                <i class="fa-solid fa-city text-lg"></i>
                <span class="text-[10px] mt-0.5">Kamar</span>
            </a>

            <a href="bills.php" class="flex flex-col items-center justify-center py-1 rounded-xl relative transition-all <?= ($currentPage ?? '') === 'bills.php' ? 'text-indigo-600 dark:text-indigo-400 font-bold' : 'text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' ?>">
                <i class="fa-solid fa-receipt text-lg"></i>
                <span class="text-[10px] mt-0.5">Tagihan</span>
                <?php if (!empty($unverifiedCount) && $unverifiedCount > 0): ?>
                    <span class="absolute top-0.5 right-3 w-4 h-4 rounded-full bg-amber-500 text-slate-950 text-[9px] font-extrabold flex items-center justify-center animate-pulse">
                        <?= $unverifiedCount ?>
                    </span>
                <?php endif; ?>
            </a>

            <a href="complaints.php" class="flex flex-col items-center justify-center py-1 rounded-xl relative transition-all <?= ($currentPage ?? '') === 'complaints.php' ? 'text-indigo-600 dark:text-indigo-400 font-bold' : 'text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' ?>">
                <i class="fa-solid fa-screwdriver-wrench text-lg"></i>
                <span class="text-[10px] mt-0.5">Komplain</span>
                <?php if (!empty($pendingComplaintsCount) && $pendingComplaintsCount > 0): ?>
                    <span class="absolute top-0.5 right-3 w-4 h-4 rounded-full bg-rose-500 text-white text-[9px] font-extrabold flex items-center justify-center">
                        <?= $pendingComplaintsCount ?>
                    </span>
                <?php endif; ?>
            </a>

            <a href="profile.php" class="flex flex-col items-center justify-center py-1 rounded-xl transition-all <?= ($currentPage ?? '') === 'profile.php' ? 'text-indigo-600 dark:text-indigo-400 font-bold' : 'text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' ?>">
                <i class="fa-solid fa-user-gear text-lg"></i>
                <span class="text-[10px] mt-0.5">Profil</span>
            </a>
        </div>
    </nav>

    <!-- Mobile Drawer & Global Scripts -->
    <script>
        function openMobileMenu() {
            const sidebar = document.getElementById('sidebarNav');
            const backdrop = document.getElementById('mobileSidebarBackdrop');
            if (sidebar) sidebar.classList.remove('-translate-x-full');
            if (backdrop) backdrop.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        }

        function closeMobileMenu() {
            const sidebar = document.getElementById('sidebarNav');
            const backdrop = document.getElementById('mobileSidebarBackdrop');
            if (sidebar) sidebar.classList.add('-translate-x-full');
            if (backdrop) backdrop.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeMobileMenu();
        });

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
