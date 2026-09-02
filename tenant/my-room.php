<?php
$pageTitle = 'Detail Kamar & Fasilitas';
require_once __DIR__ . '/header.php';

// Fetch property rules
$propertyRules = '';
if ($activeLease) {
    $stmtProp = $pdo->prepare("SELECT rules, description FROM properties WHERE name = ? LIMIT 1");
    $stmtProp->execute([$activeLease['property_name']]);
    $pData = $stmtProp->fetch();
    $propertyRules = $pData['rules'] ?? '';
}
?>

<?php if ($activeLease): ?>
    <?php $roomPhotoUrl = getRoomImage($activeLease['room_image'] ?? null, $activeLease['room_type']); ?>
    <div class="space-y-6">
        
        <!-- Room Banner Card with Photo -->
        <div class="glass-card rounded-3xl overflow-hidden border border-emerald-200 dark:border-emerald-500/30 bg-white dark:bg-slate-900 shadow-sm">
            <div class="grid grid-cols-1 md:grid-cols-12 gap-0">
                
                <!-- Room Photo -->
                <div class="md:col-span-5 h-64 md:h-auto relative overflow-hidden bg-slate-200 dark:bg-slate-800">
                    <img src="<?= htmlspecialchars($roomPhotoUrl) ?>" alt="Foto Kamar" class="w-full h-full object-cover">
                    <div class="absolute inset-0 bg-gradient-to-t from-slate-950/70 via-transparent to-transparent"></div>
                    <div class="absolute bottom-3 left-4 text-white">
                        <span class="px-2.5 py-1 rounded-full bg-emerald-600/90 text-white text-[11px] font-bold uppercase tracking-wider">
                            <i class="fa-solid fa-circle-check mr-1"></i> Kamar Anda
                        </span>
                    </div>
                </div>

                <!-- Info Details -->
                <div class="md:col-span-7 p-6 sm:p-8 flex flex-col justify-between space-y-6">
                    <div>
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-50 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 text-xs font-bold uppercase tracking-wider mb-2 border border-emerald-200 dark:border-emerald-500/30">
                            <i class="fa-solid fa-circle-check"></i> Status Sewa Aktif
                        </div>
                        <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 dark:text-white font-heading">
                            Kamar <?= htmlspecialchars($activeLease['room_number']) ?>
                        </h2>
                        <div class="text-slate-600 dark:text-slate-300 text-sm mt-1 font-semibold">
                            <?= htmlspecialchars(formatTitleCase($activeLease['property_name'])) ?> &bull; <?= htmlspecialchars(formatTitleCase($activeLease['room_type'])) ?>
                        </div>
                        <div class="text-xs text-slate-500 dark:text-slate-400 mt-2 flex items-center gap-1.5">
                            <i class="fa-solid fa-location-dot text-amber-500"></i> <?= htmlspecialchars($activeLease['property_address']) ?>
                        </div>
                    </div>

                    <div class="bg-slate-50 dark:bg-slate-950 p-4 rounded-2xl border border-slate-200 dark:border-slate-800 flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <div class="text-[11px] text-slate-500">Tarif Sewa:</div>
                            <div class="text-xl font-extrabold text-emerald-600 dark:text-emerald-400 font-heading">
                                <?= formatRupiah($activeLease['price']) ?> <span class="text-xs font-normal text-slate-500">/<?= $activeLease['rent_type'] ?></span>
                            </div>
                        </div>
                        <div class="text-xs text-slate-600 dark:text-slate-300 border-l border-slate-200 dark:border-slate-800 pl-4">
                            Masa Sewa: <strong class="text-slate-900 dark:text-white"><?= formatDateIndo($activeLease['start_date']) ?></strong> s/d <strong class="text-amber-600 dark:text-amber-400"><?= formatDateIndo($activeLease['end_date']) ?></strong>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            
            <!-- Facilities Card -->
            <div class="glass-card rounded-3xl p-6 border border-slate-200 dark:border-slate-800 space-y-4 bg-white dark:bg-slate-900 shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-lg">
                        <i class="fa-solid fa-couch"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white font-heading">Fasilitas Kamar</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Kelengkapan fasilitas yang disediakan pada unit Anda</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 pt-2">
                    <?php 
                    $facs = explode(',', $activeLease['facilities'] ?? '');
                    foreach ($facs as $f): 
                        if (trim($f)):
                    ?>
                        <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 flex items-center gap-2.5 text-xs text-slate-700 dark:text-slate-200">
                            <i class="fa-solid fa-circle-check text-emerald-500 dark:text-emerald-400"></i>
                            <span><?= htmlspecialchars(trim($f)) ?></span>
                        </div>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                </div>
            </div>

            <!-- Rules & Landlord Card -->
            <div class="glass-card rounded-3xl p-6 border border-slate-200 dark:border-slate-800 space-y-5 bg-white dark:bg-slate-900 shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center text-lg">
                        <i class="fa-solid fa-book-bookmark"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white font-heading">Aturan & Tata Tertib Kos</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Kenyamanan dan ketertiban bersama seluruh penghuni</p>
                    </div>
                </div>

                <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-xs text-slate-600 dark:text-slate-300 whitespace-pre-line leading-relaxed">
                    <?= !empty($propertyRules) ? htmlspecialchars($propertyRules) : "1. Menjaga ketenangan dan kebersihan lingkungan kos.\n2. Dilarang merokok di dalam ruangan ber-AC.\n3. Pembayaran tagihan tepat waktu sebelum tanggal jatuh tempo.\n4. Tamu menginap wajib konfirmasi kepada pengelola." ?>
                </div>

                <!-- Landlord contact box -->
                <div class="p-4 rounded-2xl bg-indigo-50 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-500/30 flex items-center justify-between">
                    <div>
                        <div class="text-xs text-indigo-700 dark:text-indigo-300 font-semibold">Pemilik / Pengelola Properti:</div>
                        <div class="text-sm font-bold text-slate-900 dark:text-white"><?= htmlspecialchars($activeLease['owner_name']) ?></div>
                    </div>
                    <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $activeLease['owner_phone']) ?>" target="_blank" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs flex items-center gap-1.5 shadow-sm">
                        <i class="fa-brands fa-whatsapp text-sm"></i> WhatsApp
                    </a>
                </div>
            </div>

        </div>
    </div>
<?php else: ?>
    <div class="p-8 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-center py-16 shadow-sm">
        <i class="fa-solid fa-bed text-5xl text-slate-400 dark:text-slate-600 mb-3"></i>
        <h3 class="text-xl font-bold text-slate-900 dark:text-white">Anda Belum Menempati Kamar</h3>
        <p class="text-slate-500 dark:text-slate-400 text-sm mt-1">Silakan hubungi pemilik atau pengelola kos untuk menghubungkan kontrak kamar sewa Anda.</p>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>
