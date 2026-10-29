<?php /** Global search results. */ ?>
<div class="page-head">
    <div><h1>Hasil Pencarian</h1><div class="sub">Kata kunci: “<?= e($query) ?>”</div></div>
</div>

<?php if ($query === ''): ?>
    <div class="card"><div class="empty-state"><?= icon('search', 'ico') ?><h3>Mulai mencari</h3><p>Gunakan kotak pencarian di atas untuk mencari NIK, nama karyawan, departemen, atau unit bisnis.</p></div></div>
<?php else: ?>
    <section class="card">
        <div class="card__head"><h2>Karyawan (<?= count($results['employees']) ?>)</h2></div>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Nama</th><th>NIK</th><th>Unit / Departemen</th><th>Email</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($results['employees'] as $emp): ?>
                    <tr>
                        <td><b><?= e($emp['full_name']) ?></b></td>
                        <td class="mono small"><?= e($emp['employee_code']) ?></td>
                        <td class="small"><?= e($emp['unit_name'] ?? '-') ?> · <?= e($emp['department_name'] ?? '-') ?></td>
                        <td class="small"><?= e($emp['email']) ?></td>
                        <td class="text-right"><a class="btn btn-outline btn-sm" href="<?= url('employees/' . $emp['id']) ?>">Buka</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$results['employees']): ?>
                    <tr><td colspan="5"><div class="table-empty">Tidak ada karyawan yang cocok.</div></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <?php if ($results['departments'] || $results['units']): ?>
        <section class="card mt-16">
            <div class="card__head"><h2>Unit &amp; Departemen</h2></div>
            <div class="card__body">
                <div class="flex wrap gap-8">
                    <?php foreach ($results['units'] as $u): ?>
                        <span class="badge badge-teal"><?= e($u['name']) ?> (<?= e($u['city']) ?>)</span>
                    <?php endforeach; ?>
                    <?php foreach ($results['departments'] as $d): ?>
                        <span class="badge badge-slate"><?= e($d['name']) ?> · <?= e($d['unit_name']) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>
<?php endif; ?>
