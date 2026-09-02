// Frontend Interactive JavaScript for LOCK & ROOM (L n' R)

document.addEventListener('DOMContentLoaded', () => {
    // Mobile Menu Toggle
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const mobileMenu = document.getElementById('mobileMenu');
    if (mobileMenuBtn && mobileMenu) {
        mobileMenuBtn.addEventListener('click', () => {
            mobileMenu.classList.toggle('hidden');
        });
    }

    // Interactive Tabs for User Guide (Tata Cara Penggunaan)
    const guideTabs = document.querySelectorAll('.guide-tab');
    const guideContents = document.querySelectorAll('.guide-content');

    guideTabs.forEach(tab => {
        tab.addEventListener('click', () => {
            const target = tab.dataset.target;
            
            guideTabs.forEach(t => {
                t.classList.remove('active', 'bg-indigo-600', 'text-white', 'shadow-lg');
                t.classList.add('bg-slate-800', 'text-slate-400');
            });
            tab.classList.add('active', 'bg-indigo-600', 'text-white', 'shadow-lg');
            tab.classList.remove('bg-slate-800', 'text-slate-400');

            guideContents.forEach(content => {
                if (content.id === target) {
                    content.classList.remove('hidden');
                    content.classList.add('animate-fadeIn');
                } else {
                    content.classList.add('hidden');
                    content.classList.remove('animate-fadeIn');
                }
            });
        });
    });

    // Auth Modal Controller
    const authModal = document.getElementById('authModal');
    const authModalOverlay = document.getElementById('authModalOverlay');
    const authModalClose = document.getElementById('authModalClose');
    const openAuthBtns = document.querySelectorAll('.open-auth-modal');

    const modalRoleTitle = document.getElementById('modalRoleTitle');
    const modalRoleBadge = document.getElementById('modalRoleBadge');
    const modalLoginBtn = document.getElementById('modalLoginBtn');
    const modalRegisterBtn = document.getElementById('modalRegisterBtn');
    const modalRoleInput = document.getElementById('modalRoleInput');

    if (authModal && openAuthBtns.length > 0) {
        openAuthBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const role = btn.dataset.role || 'pemilik';
                const isOwner = role === 'pemilik';

                if (modalRoleTitle) {
                    modalRoleTitle.innerText = isOwner ? 'Portal Pemilik Kos / Kontrakan' : 'Portal Penyewa Kamar';
                }
                if (modalRoleBadge) {
                    modalRoleBadge.innerText = isOwner ? 'PEMILIK / OWNER' : 'PENYEWA / TENANT';
                    modalRoleBadge.className = isOwner 
                        ? 'px-3 py-1 text-xs font-bold uppercase rounded-full bg-indigo-500/20 text-indigo-400 border border-indigo-500/30'
                        : 'px-3 py-1 text-xs font-bold uppercase rounded-full bg-emerald-500/20 text-emerald-400 border border-emerald-500/30';
                }
                if (modalLoginBtn) {
                    modalLoginBtn.href = `auth/login.php?role=${role}`;
                }
                if (modalRegisterBtn) {
                    modalRegisterBtn.href = `auth/register.php?role=${role}`;
                }

                authModal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            });
        });

        const closeModal = () => {
            authModal.classList.add('hidden');
            document.body.style.overflow = 'auto';
        };

        if (authModalOverlay) authModalOverlay.addEventListener('click', closeModal);
        if (authModalClose) authModalClose.addEventListener('click', closeModal);
    }

    // Filter Rooms Showcase
    const roomFilterBtns = document.querySelectorAll('.room-filter-btn');
    const roomCards = document.querySelectorAll('.room-card-item');

    roomFilterBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const filter = btn.dataset.filter;

            roomFilterBtns.forEach(b => {
                b.classList.remove('bg-amber-500', 'text-slate-900', 'font-bold');
                b.classList.add('bg-slate-800', 'text-slate-300');
            });
            btn.classList.add('bg-amber-500', 'text-slate-900', 'font-bold');
            btn.classList.remove('bg-slate-800', 'text-slate-300');

            roomCards.forEach(card => {
                if (filter === 'all' || card.dataset.status === filter) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    });
});
