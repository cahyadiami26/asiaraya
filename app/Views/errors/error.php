<div class="card" style="max-width:520px;margin:60px auto;text-align:center">
    <div class="card__body" style="padding:40px 30px">
        <div class="empty-state" style="padding:0">
            <div class="empty-icon" style="width:56px;height:56px"><?= icon('alert', 'ico') ?></div>
            <h3 style="font-size:20px"><?= (int) ($code ?? 500) ?> · Terjadi Kesalahan</h3>
            <p><?= e($message ?: 'Sistem tidak dapat memproses permintaan Anda saat ini.') ?></p>
            <a class="btn btn-primary mt-16" href="<?= url('dashboard') ?>"><?= icon('home') ?> Kembali ke Dashboard</a>
        </div>
    </div>
</div>
