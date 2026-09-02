// Comprehensive Realtime Notification Engine for LOCK & ROOM (L n' R)
// Supports both OWNER (Incoming Bills & Complaints) and TENANT (Approval/LUNAS Alerts)
// Works across Web Browser, Mobile Screen Notifications, & Capacitor Android APK

class LockRoomNotifier {
    constructor() {
        this.role = window.LOCKROOM_USER_ROLE || 'pemilik';
        this.lastBillCount = null;
        this.lastComplaintCount = null;
        this.lastApprovedCount = null;
        this.isPolling = false;
        this.init();
    }

    async init() {
        // Request notification permission if supported
        if ('Notification' in window && Notification.permission === 'default') {
            this.showPermissionBanner();
        }

        // Initialize Capacitor Native Android Notification if running in APK
        if (window.Capacitor && window.Capacitor.Plugins) {
            this.initCapacitorAndroid();
        }

        // Start Polling every 7 seconds
        this.startPolling();
    }

    async initCapacitorAndroid() {
        try {
            const { PushNotifications, LocalNotifications } = window.Capacitor.Plugins;
            if (LocalNotifications) {
                await LocalNotifications.requestPermissions();
            }
            if (PushNotifications) {
                await PushNotifications.requestPermissions();
                await PushNotifications.register();
            }
        } catch (e) {
            // Native plugins fallback
        }
    }

    showPermissionBanner() {
        if (document.getElementById('notifPermissionBanner')) return;

        const banner = document.createElement('div');
        banner.id = 'notifPermissionBanner';
        banner.className = 'fixed bottom-4 right-4 z-50 p-4 max-w-sm rounded-2xl bg-white dark:bg-slate-900 border border-indigo-200 dark:border-indigo-500/40 shadow-2xl flex items-start gap-3.5 transform transition-all duration-300';
        
        const bannerDesc = this.role === 'pemilik' 
            ? 'Dapatkan pop-up instan di layar HP saat ada pembayaran baru atau laporan keluhan fasilitas kos dari penghuni.'
            : 'Dapatkan pop-up instan di layar HP saat pembayaran sewa Anda telah disetujui (LUNAS) atau keluhan Anda ditanggapi pemilik.';

        banner.innerHTML = `
            <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-500/20 text-indigo-600 dark:text-indigo-400 flex items-center justify-center flex-shrink-0 text-lg">
                <i class="fa-solid fa-bell"></i>
            </div>
            <div class="flex-1">
                <div class="text-xs font-bold text-slate-900 dark:text-white font-heading">Aktifkan Notifikasi Layar HP</div>
                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 leading-relaxed">
                    ${bannerDesc}
                </p>
                <div class="mt-2.5 flex items-center gap-2">
                    <button id="btnAllowNotif" class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-[11px] shadow-sm">
                        Izinkan Pop-Up
                    </button>
                    <button id="btnDismissNotif" class="px-2.5 py-1.5 rounded-lg text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 text-[11px]">
                        Nanti
                    </button>
                </div>
            </div>
        `;
        document.body.appendChild(banner);

        document.getElementById('btnAllowNotif').onclick = () => {
            this.requestPermission();
            banner.remove();
        };
        document.getElementById('btnDismissNotif').onclick = () => {
            banner.remove();
        };
    }

    async requestPermission() {
        if ('Notification' in window) {
            const permission = await Notification.requestPermission();
            if (permission === 'granted') {
                this.playChimeSound();
                this.fireNotification('Notifikasi Diaktifkan!', 'Anda akan menerima pop-up layar HP untuk pembaruan status pembayaran dan kos.');
            }
        }
    }

    startPolling() {
        if (this.isPolling) return;
        this.isPolling = true;

        // Immediate check
        this.checkEvents();

        // Polling interval every 7 seconds
        setInterval(() => {
            this.checkEvents();
        }, 7000);
    }

    async checkEvents() {
        try {
            const response = await fetch('../helpers/api_notifications.php');
            if (!response.ok) return;

            const res = await response.json();
            if (res.status !== 'success') return;

            // ================= 1. OWNER ROLE DISPATCH =================
            if (res.role === 'pemilik') {
                this.handleOwnerEvents(res);
                return;
            }

            // ================= 2. TENANT ROLE DISPATCH =================
            if (res.role === 'penyewa') {
                this.handleTenantEvents(res);
                return;
            }

        } catch (e) {
            // Polling error silently caught
        }
    }

    // =========================================================================
    // OWNER EVENTS HANDLER
    // =========================================================================
    handleOwnerEvents(res) {
        const billCount = res.bill_count || 0;
        const complaintCount = res.complaint_count || 0;
        const totalCount = res.total_count || 0;

        // Update header badge
        const badgeElem = document.getElementById('headerNotifBadge');
        if (badgeElem) {
            if (totalCount > 0) {
                badgeElem.innerText = totalCount;
                badgeElem.classList.remove('hidden');
            } else {
                badgeElem.classList.add('hidden');
            }
        }

        // Initial Login
        if (this.lastBillCount === null || this.lastComplaintCount === null) {
            this.lastBillCount = billCount;
            this.lastComplaintCount = complaintCount;

            if (totalCount > 0 && !sessionStorage.getItem('notified_login_owner')) {
                sessionStorage.setItem('notified_login_owner', 'true');
                this.playChimeSound();
                this.vibrateDevice();

                let summaryHtml = '';
                if (billCount > 0) {
                    const latestBill = res.bills[0];
                    summaryHtml += `
                        <div class="p-3 bg-amber-50 dark:bg-amber-500/10 rounded-xl border border-amber-200 dark:border-amber-500/30">
                            <div class="text-amber-800 dark:text-amber-300 font-bold flex items-center gap-1.5">
                                <i class="fa-solid fa-file-invoice-dollar"></i> ${billCount} Pembayaran Menunggu Verifikasi
                            </div>
                            <div class="text-[11px] text-slate-600 dark:text-slate-300 mt-1">
                                Penyewa: <strong>${latestBill.tenant_name}</strong> (Kamar ${latestBill.room_number}) • <strong>Rp ${Number(latestBill.amount).toLocaleString('id-ID')}</strong>
                            </div>
                        </div>
                    `;
                }

                if (complaintCount > 0) {
                    const latestComp = res.complaints[0];
                    summaryHtml += `
                        <div class="p-3 bg-rose-50 dark:bg-rose-500/10 rounded-xl border border-rose-200 dark:border-rose-500/30">
                            <div class="text-rose-800 dark:text-rose-300 font-bold flex items-center gap-1.5">
                                <i class="fa-solid fa-screwdriver-wrench"></i> ${complaintCount} Pengaduan Fasilitas Baru
                            </div>
                            <div class="text-[11px] text-slate-600 dark:text-slate-300 mt-1">
                                <strong>${latestComp.title}</strong> oleh <strong>${latestComp.tenant_name}</strong> (Kamar ${latestComp.room_number})
                            </div>
                        </div>
                    `;
                }

                this.fireNotification(
                    `📋 Ada ${totalCount} Aktivitas Memerlukan Tindakan`,
                    `${billCount} pembayaran & ${complaintCount} pengaduan fasilitas kos.`
                );

                if (typeof Swal !== 'undefined') {
                    setTimeout(() => {
                        Swal.fire({
                            title: `Ada ${totalCount} Aktivitas Perlu Ditindaklanjuti`,
                            html: `<div class="text-left text-xs space-y-2.5 mt-2">${summaryHtml}</div>`,
                            icon: 'info',
                            showCancelButton: true,
                            confirmButtonText: billCount > 0 ? '<i class="fa-solid fa-file-circle-check mr-1"></i> Buka Menu Tagihan' : '<i class="fa-solid fa-screwdriver-wrench mr-1"></i> Buka Pengaduan',
                            cancelButtonText: 'Nanti',
                            confirmButtonColor: '#4f46e5',
                            cancelButtonColor: '#64748b'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                window.location.href = billCount > 0 ? 'bills.php' : 'complaints.php';
                            }
                        });
                    }, 500);
                }
            }
            return;
        }

        // Realtime Payment Arrival
        if (billCount > this.lastBillCount && billCount > 0) {
            const latestBill = res.bills[0];
            const tenantName = latestBill ? latestBill.tenant_name : 'Penyewa';
            const roomNo = latestBill ? latestBill.room_number : '-';
            const amountFormatted = latestBill ? 'Rp ' + Number(latestBill.amount).toLocaleString('id-ID') : '';

            this.playChimeSound();
            this.vibrateDevice();

            this.fireNotification(
                `🔔 Pembayaran Baru Masuk!`,
                `${tenantName} (Kamar ${roomNo}) mengunggah bukti transfer ${amountFormatted}. Ketuk untuk verifikasi.`,
                'bills.php'
            );

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Pembayaran Baru Masuk!',
                    html: `
                        <div class="text-left text-xs text-slate-600 dark:text-slate-300 space-y-2 p-3.5 bg-slate-50 dark:bg-slate-800/80 rounded-2xl border border-slate-200 dark:border-slate-700 mt-2">
                            <div>Penyewa: <strong class="text-slate-900 dark:text-white">${tenantName} (Kamar ${roomNo})</strong></div>
                            <div>Nominal: <strong class="text-emerald-600 dark:text-emerald-400 font-bold">${amountFormatted}</strong></div>
                            <div class="text-[11px] text-slate-500 dark:text-slate-400 pt-1 border-t border-slate-200 dark:border-slate-700">
                                Silakan periksa foto bukti transfer dan setujui (Approve) status LUNAS.
                            </div>
                        </div>
                    `,
                    icon: 'info',
                    showCancelButton: true,
                    confirmButtonText: '<i class="fa-solid fa-file-circle-check mr-1"></i> Verifikasi Sekarang',
                    cancelButtonText: 'Nanti',
                    confirmButtonColor: '#4f46e5',
                    cancelButtonColor: '#64748b'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = 'bills.php';
                    }
                });
            }
        }

        // Realtime Complaint Arrival
        if (complaintCount > this.lastComplaintCount && complaintCount > 0) {
            const latestComp = res.complaints[0];
            const tenantName = latestComp ? latestComp.tenant_name : 'Penyewa';
            const roomNo = latestComp ? latestComp.room_number : '-';
            const title = latestComp ? latestComp.title : 'Kendala Fasilitas';

            this.playWarningSound();
            this.vibrateDevice();

            this.fireNotification(
                `⚠️ Pengaduan Fasilitas Baru!`,
                `${tenantName} (Kamar ${roomNo}): "${title}". Ketuk untuk tanggapi.`,
                'complaints.php'
            );

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Pengaduan Fasilitas Baru Masuk!',
                    html: `
                        <div class="text-left text-xs text-slate-600 dark:text-slate-300 space-y-2 p-3.5 bg-rose-50 dark:bg-rose-500/10 rounded-2xl border border-rose-200 dark:border-rose-500/30 mt-2">
                            <div>Penyewa: <strong class="text-slate-900 dark:text-white">${tenantName} (Kamar ${roomNo})</strong></div>
                            <div>Masalah: <strong class="text-rose-600 dark:text-rose-400 font-bold">${title}</strong></div>
                        </div>
                    `,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: '<i class="fa-solid fa-screwdriver-wrench mr-1"></i> Tanggapi Sekarang',
                    cancelButtonText: 'Nanti',
                    confirmButtonColor: '#e11d48',
                    cancelButtonColor: '#64748b'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = 'complaints.php';
                    }
                });
            }
        }

        this.lastBillCount = billCount;
        this.lastComplaintCount = complaintCount;
    }

    // =========================================================================
    // TENANT EVENTS HANDLER (APPROVAL / LUNAS NOTIFICATIONS)
    // =========================================================================
    handleTenantEvents(res) {
        const approvedCount = res.approved_count || 0;
        const rejectedCount = res.rejected_count || 0;

        // Check if a payment was just approved by owner
        if (this.lastApprovedCount !== null && approvedCount > this.lastApprovedCount) {
            const latestApproved = res.approved_bills[0];
            const billTitle = latestApproved ? latestApproved.title : 'Tagihan Sewa';
            const billAmount = latestApproved ? 'Rp ' + Number(latestApproved.amount).toLocaleString('id-ID') : '';

            this.playChimeSound();
            this.vibrateDevice();

            // 1. Screen & Lockscreen Pop-up Notification
            this.fireNotification(
                `🎉 Pembayaran Anda Telah Diverifikasi LUNAS!`,
                `Pemilik kos telah menyetujui pembayaran ${billTitle} sebesar ${billAmount}.`,
                'bills.php'
            );

            // 2. In-App SweetAlert Pop-up Modal Dialog
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Pembayaran Diverifikasi LUNAS!',
                    html: `
                        <div class="text-left text-xs text-slate-600 dark:text-slate-300 space-y-2 p-3.5 bg-emerald-50 dark:bg-emerald-500/10 rounded-2xl border border-emerald-200 dark:border-emerald-500/30 mt-2">
                            <div class="flex items-center justify-between">
                                <span>Status Tagihan:</span>
                                <span class="px-2.5 py-0.5 rounded-full bg-emerald-600 text-white font-bold text-[10px] uppercase">
                                    <i class="fa-solid fa-check mr-1"></i> LUNAS
                                </span>
                            </div>
                            <div>Tagihan: <strong class="text-slate-900 dark:text-white">${billTitle}</strong></div>
                            <div>Nominal: <strong class="text-emerald-600 dark:text-emerald-400 font-bold">${billAmount}</strong></div>
                            <div class="text-[11px] text-slate-500 dark:text-slate-400 pt-1 border-t border-emerald-200 dark:border-emerald-500/30">
                                Bukti transfer Anda telah disetujui secara resmi oleh pemilik kos.
                            </div>
                        </div>
                    `,
                    icon: 'success',
                    showCancelButton: true,
                    confirmButtonText: '<i class="fa-solid fa-receipt mr-1"></i> Lihat Kwitansi Pembayaran',
                    cancelButtonText: 'Tutup',
                    confirmButtonColor: '#059669',
                    cancelButtonColor: '#64748b'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = 'bills.php';
                    }
                });
            }
        }

        this.lastApprovedCount = approvedCount;
    }

    async fireNotification(title, body, url = 'bills.php') {
        // 1. If running as Capacitor Native Android APK
        if (window.Capacitor && window.Capacitor.Plugins && window.Capacitor.Plugins.LocalNotifications) {
            try {
                await window.Capacitor.Plugins.LocalNotifications.schedule({
                    notifications: [
                        {
                            title: title,
                            body: body,
                            id: Math.floor(Math.random() * 10000),
                            schedule: { at: new Date(Date.now() + 100) },
                            sound: 'beep.wav',
                            smallIcon: 'ic_stat_lockroom',
                            extra: { url: url }
                        }
                    ]
                });
                return;
            } catch (err) {
                // Fallback to standard web notification
            }
        }

        // 2. Standard Web Push Notification API
        if ('Notification' in window && Notification.permission === 'granted') {
            try {
                const notif = new Notification(title, {
                    body: body,
                    icon: '../assets/images/icon-192.png',
                    badge: '../assets/images/icon-192.png',
                    vibrate: [200, 100, 200],
                    tag: 'lockroom-event-' + Date.now(),
                    requireInteraction: true
                });

                notif.onclick = function() {
                    window.focus();
                    if (url) window.location.href = url;
                    notif.close();
                };
            } catch (e) {
                if (navigator.serviceWorker && navigator.serviceWorker.ready) {
                    navigator.serviceWorker.ready.then(registration => {
                        registration.showNotification(title, {
                            body: body,
                            icon: '../assets/images/icon-192.png',
                            vibrate: [200, 100, 200],
                            data: { url: url }
                        });
                    });
                }
            }
        }
    }

    playChimeSound() {
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) return;

            const ctx = new AudioContext();
            
            // Note 1: E5
            const osc1 = ctx.createOscillator();
            const gain1 = ctx.createGain();
            osc1.type = 'sine';
            osc1.frequency.setValueAtTime(659.25, ctx.currentTime);
            gain1.gain.setValueAtTime(0.3, ctx.currentTime);
            gain1.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.5);
            osc1.connect(gain1);
            gain1.connect(ctx.destination);
            osc1.start(ctx.currentTime);
            osc1.stop(ctx.currentTime + 0.5);

            // Note 2: B5
            const osc2 = ctx.createOscillator();
            const gain2 = ctx.createGain();
            osc2.type = 'sine';
            osc2.frequency.setValueAtTime(987.77, ctx.currentTime + 0.15);
            gain2.gain.setValueAtTime(0.4, ctx.currentTime + 0.15);
            gain2.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.8);
            osc2.connect(gain2);
            gain2.connect(ctx.destination);
            osc2.start(ctx.currentTime + 0.15);
            osc2.stop(ctx.currentTime + 0.8);
        } catch (e) {}
    }

    playWarningSound() {
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) return;

            const ctx = new AudioContext();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'triangle';
            osc.frequency.setValueAtTime(440, ctx.currentTime);
            osc.frequency.setValueAtTime(880, ctx.currentTime + 0.15);
            gain.gain.setValueAtTime(0.3, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.6);
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start(ctx.currentTime);
            osc.stop(ctx.currentTime + 0.6);
        } catch (e) {}
    }

    vibrateDevice() {
        if ('vibrate' in navigator) {
            try {
                navigator.vibrate([200, 100, 200]);
            } catch (e) {}
        }
    }
}

// Auto-initialize when DOM loaded
document.addEventListener('DOMContentLoaded', () => {
    window.lockRoomNotifier = new LockRoomNotifier();
});
