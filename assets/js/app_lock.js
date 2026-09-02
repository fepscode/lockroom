// App Lock Screen Engine - LOCK & ROOM (L n' R)
// Uses existing login password for fast & secure verification

class LockRoomAppLock {
    constructor() {
        this.overlay = null;
        this.idleTimeout = null;
        this.idleLimitMinutes = 5; // Auto-lock after 5 minutes of inactivity
        this.init();
    }

    init() {
        this.createOverlayDOM();
        this.bindEvents();
        this.resetIdleTimer();

        // Check if session is already locked from server or localStorage
        if (sessionStorage.getItem('lockroom_is_locked') === 'true') {
            this.showLockScreen();
        }
    }

    createOverlayDOM() {
        if (document.getElementById('appLockOverlay')) {
            this.overlay = document.getElementById('appLockOverlay');
            return;
        }

        const overlay = document.createElement('div');
        overlay.id = 'appLockOverlay';
        overlay.className = 'fixed inset-0 z-[9999] bg-slate-950/90 backdrop-blur-xl hidden flex items-center justify-center p-4 selection:bg-indigo-500 selection:text-white';
        
        const userName = window.LOCKROOM_USER_NAME || 'Pemilik Kos';
        const userEmail = window.LOCKROOM_USER_EMAIL || '';
        const userRole = window.LOCKROOM_USER_ROLE === 'pemilik' ? 'Portal Pemilik Kos' : 'Portal Penyewa';

        overlay.innerHTML = `
            <div class="max-w-md w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-6 sm:p-8 shadow-2xl text-center space-y-6 transform transition-all animate-in fade-in zoom-in-95 duration-200">
                
                <!-- Lock Icon / Avatar -->
                <div class="relative mx-auto w-20 h-20">
                    <div class="w-20 h-20 rounded-2xl bg-gradient-to-tr from-amber-500 via-indigo-600 to-indigo-700 flex items-center justify-center text-white text-3xl shadow-lg shadow-indigo-600/30">
                        <i class="fa-solid fa-lock"></i>
                    </div>
                    <div class="absolute -bottom-1 -right-1 w-7 h-7 rounded-full bg-emerald-500 border-2 border-white dark:border-slate-900 flex items-center justify-center text-white text-[11px]">
                        <i class="fa-solid fa-shield-halved"></i>
                    </div>
                </div>

                <!-- Text Headings -->
                <div class="space-y-1">
                    <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400 text-[11px] font-bold uppercase tracking-wider">
                        <i class="fa-solid fa-shield-cat"></i> Aplikasi Terkunci
                    </div>
                    <h2 class="text-2xl font-extrabold text-slate-900 dark:text-white font-heading">${userName}</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400 font-mono">${userEmail}</p>
                    <p class="text-xs text-slate-600 dark:text-slate-300 pt-2">
                        Masukkan <strong>kata sandi login</strong> Anda untuk membuka kunci aplikasi:
                    </p>
                </div>

                <!-- Unlock Form -->
                <form id="formUnlockApp" class="space-y-4 text-left">
                    <div id="unlockAlertError" class="hidden p-3 rounded-xl bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30 text-rose-600 dark:text-rose-400 text-xs font-semibold text-center">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">Kata Sandi Login</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-slate-400">
                                <i class="fa-solid fa-key text-xs"></i>
                            </span>
                            <input type="password" id="inputUnlockPassword" required placeholder="Masukkan kata sandi..." autocomplete="current-password" class="w-full bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 rounded-xl py-3 pl-10 pr-10 text-slate-900 dark:text-white text-sm focus:border-indigo-500 focus:outline-none transition-colors">
                            <button type="button" id="btnToggleUnlockPwd" class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                                <i class="fa-solid fa-eye text-xs"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" id="btnSubmitUnlock" class="w-full py-3.5 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs shadow-lg shadow-indigo-600/30 transition-all flex items-center justify-center gap-2">
                        <i class="fa-solid fa-lock-open"></i>
                        <span>Buka Kunci Aplikasi</span>
                    </button>
                </form>

                <!-- Footer Links -->
                <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-xs text-slate-500">
                    <a href="../auth/forgot-password.php" class="hover:text-indigo-600 dark:hover:text-amber-400 transition-colors">Lupa Password?</a>
                    <a href="../auth/logout.php" class="text-rose-500 hover:underline flex items-center gap-1">
                        <i class="fa-solid fa-power-off text-[10px]"></i> Keluar / Ganti Akun
                    </a>
                </div>

            </div>
        `;

        document.body.appendChild(overlay);
        this.overlay = overlay;
    }

    bindEvents() {
        // Toggle Password visibility
        const btnToggle = document.getElementById('btnToggleUnlockPwd');
        const inputPwd = document.getElementById('inputUnlockPassword');
        if (btnToggle && inputPwd) {
            btnToggle.onclick = () => {
                if (inputPwd.type === 'password') {
                    inputPwd.type = 'text';
                    btnToggle.innerHTML = '<i class="fa-solid fa-eye-slash text-xs"></i>';
                } else {
                    inputPwd.type = 'password';
                    btnToggle.innerHTML = '<i class="fa-solid fa-eye text-xs"></i>';
                }
            };
        }

        // Form Submit
        const form = document.getElementById('formUnlockApp');
        if (form) {
            form.onsubmit = (e) => {
                e.preventDefault();
                this.handleUnlock();
            };
        }

        // Reset idle timer on user activity
        ['mousemove', 'keydown', 'touchstart', 'scroll', 'click'].forEach(evt => {
            window.addEventListener(evt, () => this.resetIdleTimer(), { passive: true });
        });
    }

    resetIdleTimer() {
        if (this.idleTimeout) clearTimeout(this.idleTimeout);
        
        // Auto lock after idle limit
        this.idleTimeout = setTimeout(() => {
            this.lock();
        }, this.idleLimitMinutes * 60 * 1000);
    }

    showLockScreen() {
        if (!this.overlay) this.createOverlayDOM();
        this.overlay.classList.remove('hidden');
        sessionStorage.setItem('lockroom_is_locked', 'true');

        const inputPwd = document.getElementById('inputUnlockPassword');
        if (inputPwd) {
            inputPwd.value = '';
            setTimeout(() => inputPwd.focus(), 200);
        }

        const alertErr = document.getElementById('unlockAlertError');
        if (alertErr) alertErr.classList.add('hidden');
    }

    hideLockScreen() {
        if (this.overlay) {
            this.overlay.classList.add('hidden');
        }
        sessionStorage.removeItem('lockroom_is_locked');
        this.resetIdleTimer();
    }

    lock() {
        fetch('../helpers/api_unlock.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=lock'
        }).catch(() => {});

        this.showLockScreen();
    }

    async handleUnlock() {
        const inputPwd = document.getElementById('inputUnlockPassword');
        const alertErr = document.getElementById('unlockAlertError');
        const btnSubmit = document.getElementById('btnSubmitUnlock');

        if (!inputPwd || !inputPwd.value) return;

        btnSubmit.disabled = true;
        btnSubmit.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Memeriksa Kata Sandi...';

        try {
            const formData = new URLSearchParams();
            formData.append('action', 'unlock');
            formData.append('password', inputPwd.value);

            const res = await fetch('../helpers/api_unlock.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString()
            });

            const data = await res.json();

            if (data.status === 'success') {
                this.hideLockScreen();
                if (typeof Swal !== 'undefined') {
                    const Toast = Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 2000,
                        timerProgressBar: true
                    });
                    Toast.fire({
                        icon: 'success',
                        title: 'Kunci Terbuka! Selamat Datang Kembali.'
                    });
                }
            } else {
                alertErr.innerText = data.message || 'Kata sandi tidak sesuai!';
                alertErr.classList.remove('hidden');
                inputPwd.value = '';
                inputPwd.focus();
            }
        } catch (e) {
            alertErr.innerText = 'Gagal menghubungi server. Silakan coba lagi.';
            alertErr.classList.remove('hidden');
        } finally {
            btnSubmit.disabled = false;
            btnSubmit.innerHTML = '<i class="fa-solid fa-lock-open"></i> <span>Buka Kunci Aplikasi</span>';
        }
    }
}

// Global functions
window.lockAppNow = function() {
    if (window.lockRoomAppLock) {
        window.lockRoomAppLock.lock();
    }
};

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    window.lockRoomAppLock = new LockRoomAppLock();
});
