        </main>

        <!-- Footer Desktop -->
        <footer class="p-6 border-t border-slate-200 dark:border-slate-800 text-center text-xs text-slate-500 hidden md:block space-y-3">
            <div>
                <a href="https://fepscode.my.id" target="_blank" rel="noopener noreferrer" class="group relative inline-flex items-center gap-2.5 px-4 py-1.5 rounded-full bg-gradient-to-r from-emerald-500/10 via-teal-500/10 to-indigo-500/10 hover:from-emerald-500/20 hover:via-teal-500/20 hover:to-indigo-500/20 border border-emerald-500/30 hover:border-emerald-500/60 shadow-sm shadow-emerald-500/10 hover:shadow-emerald-500/25 transition-all duration-300 transform hover:-translate-y-0.5">
                    <span class="relative flex h-1.5 w-1.5">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-emerald-500"></span>
                    </span>
                    <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 tracking-widest uppercase">POWERED BY</span>
                    <strong class="font-black text-xs tracking-wider bg-gradient-to-r from-emerald-600 via-teal-600 to-indigo-600 dark:from-emerald-400 dark:via-teal-300 dark:to-indigo-400 bg-clip-text text-transparent">FECO</strong>
                    <i class="fa-solid fa-arrow-up-right-from-square text-[9px] text-emerald-500 dark:text-teal-400 group-hover:translate-x-0.5 group-hover:-translate-y-0.5 transition-transform"></i>
                </a>
            </div>
            <div>&copy; <?= date('Y') ?> <strong>LOCK & ROOM (L n' R)</strong> — Panel Penghuni Kos & Kontrakan.</div>
        </footer>
    </div>

    <!-- Mobile Bottom Navigation Bar (Fixed for Phones) -->
    <nav class="md:hidden fixed bottom-0 left-0 right-0 z-30 bg-white/90 dark:bg-slate-900/90 backdrop-blur-lg border-t border-slate-200 dark:border-slate-800 px-2 py-2 shadow-lg">
        <div class="grid grid-cols-5 gap-1 items-center">
            <a href="index.php" class="flex flex-col items-center justify-center py-1 rounded-xl transition-all <?= ($currentPage ?? '') === 'index.php' ? 'text-emerald-600 dark:text-emerald-400 font-bold' : 'text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' ?>">
                <i class="fa-solid fa-gauge-high text-lg"></i>
                <span class="text-[10px] mt-0.5">Dashboard</span>
            </a>
            
            <a href="my-room.php" class="flex flex-col items-center justify-center py-1 rounded-xl transition-all <?= ($currentPage ?? '') === 'my-room.php' ? 'text-emerald-600 dark:text-emerald-400 font-bold' : 'text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' ?>">
                <i class="fa-solid fa-bed text-lg"></i>
                <span class="text-[10px] mt-0.5">Kamar</span>
            </a>

            <a href="bills.php" class="flex flex-col items-center justify-center py-1 rounded-xl relative transition-all <?= ($currentPage ?? '') === 'bills.php' ? 'text-emerald-600 dark:text-emerald-400 font-bold' : 'text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' ?>">
                <i class="fa-solid fa-file-invoice-dollar text-lg"></i>
                <span class="text-[10px] mt-0.5">Tagihan</span>
                <?php if (!empty($unpaidBillsCount) && $unpaidBillsCount > 0): ?>
                    <span class="absolute top-0.5 right-3 w-4 h-4 rounded-full bg-rose-500 text-white text-[9px] font-extrabold flex items-center justify-center animate-pulse">
                        <?= $unpaidBillsCount ?>
                    </span>
                <?php endif; ?>
            </a>

            <a href="complaints.php" class="flex flex-col items-center justify-center py-1 rounded-xl relative transition-all <?= ($currentPage ?? '') === 'complaints.php' ? 'text-emerald-600 dark:text-emerald-400 font-bold' : 'text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' ?>">
                <i class="fa-solid fa-screwdriver-wrench text-lg"></i>
                <span class="text-[10px] mt-0.5">Komplain</span>
            </a>

            <a href="profile.php" class="flex flex-col items-center justify-center py-1 rounded-xl transition-all <?= ($currentPage ?? '') === 'profile.php' ? 'text-emerald-600 dark:text-emerald-400 font-bold' : 'text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' ?>">
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
                confirmButtonColor: '#10b981'
            });
        <?php endif; ?>

        <?php if ($flashError = getFlash('error')): ?>
            Swal.fire({
                icon: 'error',
                title: 'Oops...',
                text: '<?= addslashes($flashError) ?>',
                background: '#1e293b',
                color: '#fff',
                confirmButtonColor: '#10b981'
            });
        <?php endif; ?>

        // Back to Top Scroll Logic
        const tenantBackToTop = document.getElementById('tenantBackToTop');
        if (tenantBackToTop) {
            window.addEventListener('scroll', () => {
                if (window.scrollY > 250) {
                    tenantBackToTop.classList.remove('opacity-0', 'translate-y-10', 'pointer-events-none');
                    tenantBackToTop.classList.add('opacity-100', 'translate-y-0', 'pointer-events-auto');
                } else {
                    tenantBackToTop.classList.add('opacity-0', 'translate-y-10', 'pointer-events-none');
                    tenantBackToTop.classList.remove('opacity-100', 'translate-y-0', 'pointer-events-auto');
                }
            });
        }
    </script>

    <!-- Floating Back to Top Button -->
    <button id="tenantBackToTop" onclick="window.scrollTo({top: 0, behavior: 'smooth'})" type="button" aria-label="Kembali ke Atas" class="fixed bottom-20 md:bottom-6 right-6 z-40 w-11 h-11 rounded-2xl bg-gradient-to-tr from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white shadow-xl shadow-emerald-600/40 flex items-center justify-center transition-all duration-300 opacity-0 translate-y-10 pointer-events-none group border border-white/20">
        <i class="fa-solid fa-arrow-up text-xs transition-transform duration-300 group-hover:-translate-y-0.5"></i>
    </button>
</body>
</html>
