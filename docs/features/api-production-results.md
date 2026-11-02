# API Production Results

**Status:** Live

## Summary

`POST /api/production-results` mencatat hasil produksi untuk satu Work Order dan merupakan satu-satunya operasi tulis aplikasi (INSERT ke `production_result`). Endpoint hanya menerima WO berstatus `RUNNING` yang belum punya hasil; status WO tidak diubah. Dengan data awal semua WO `RUNNING` sudah punya hasil, sehingga lewat HTTP endpoint ini selalu mengembalikan 422. Jalur sukses diuji dengan skrip `tinker` di dalam transaksi yang di-rollback (lihat [Pengujian jalur sukses](#pengujian-jalur-sukses-tinker)).

## Quick Reference

| Method | Path | Controller | Request | Service |
|---|---|---|---|---|
| POST | `/api/production-results` | `Api\ProductionResultController@store` | `StoreProductionResultRequest` | `ProductionResultService::store(array): array` |

### Body (JSON)

| Field | Validasi | Pemetaan ke soal |
|---|---|---|
| `wo_number` | required, string, ada di `work_order` | — |
| `actual_start` | required, `Y-m-d H:i:s`, ≤ waktu sekarang (`Asia/Jakarta`) | `production_date` (PRD B7) |
| `actual_finish` | required, `Y-m-d H:i:s`, setelah `actual_start`, ≤ waktu sekarang | — |
| `good_qty` | required, integer ≥ 0 | `qty_good` (PRD B8) |
| `reject_qty` | required, integer ≥ 0 | `qty_reject` (PRD B8) |

### Urutan pemeriksaan

| Tahap | Lokasi | Pemeriksaan | Gagal |
|---|---|---|---|
| 1 | `StoreProductionResultRequest::rules()` | Format dan nilai field di atas | 422, semua error field sekaligus |
| 2 | `ProductionResultService::store()` | Baris `work_order` dikunci (`lockForUpdate()`) di dalam transaksi; status harus `RUNNING` | 422 pada `wo_number` |
| 3 | `ProductionResultService::store()` | WO belum punya baris `production_result` | 422 pada `wo_number` |
| 4 | `ProductionResultService::store()` | INSERT, lalu response 201 | — |

### Nilai yang dihitung server

| Kolom | Rumus |
|---|---|
| `runtime_minutes` | Selisih menit `actual_start` → `actual_finish` (integer) |
| `achievement` | `good_qty / target_qty × 100`, `round(…, 2)`; `target_qty` 0 → 0 |

### Contoh request

```bash
curl -X POST http://localhost/api/production-results \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"wo_number":"WO2026000009","actual_start":"2026-10-01 08:00:00","actual_finish":"2026-10-01 10:30:00","good_qty":950,"reject_qty":10}'
```

Error selalu dikembalikan sebagai JSON untuk path `api/*`, dengan atau tanpa header `Accept: application/json` (`shouldRenderJsonWhen` di `bootstrap/app.php`).

### Response sukses (HTTP 201)

```json
{ "data": { "id": 1475, "wo_number": "WO-TEST-0001", "actual_start": "2026-10-01 08:00:00", "actual_finish": "2026-10-01 10:30:00", "runtime_minutes": 150, "good_qty": 950, "reject_qty": 10, "achievement": 95 } }
```

### Contoh penolakan (semua HTTP 422)

| Kasus | Body (selain field valid) | `errors` |
|---|---|---|
| WO tidak ada | `"wo_number": "WO9999"` | `wo_number`: The selected wo number is invalid. |
| Qty negatif | `"good_qty": -1, "reject_qty": -1` | `good_qty`, `reject_qty`: must be at least 0. |
| Qty bukan angka | `"reject_qty": "abc"` | `reject_qty`: must be an integer. |
| Tanggal masa depan | `"actual_start"` > sekarang | `actual_start`: must be a date before or equal to now. |
| Finish sebelum start | `"actual_finish"` < `actual_start` | `actual_finish`: must be a date after actual start. |
| Format tanggal salah | `"actual_start": "2026-10-01"` | `actual_start`: must match the format Y-m-d H:i:s. |
| WO bukan RUNNING | `"wo_number": "WO2026000005"` (OPEN) | `wo_number`: Production order must be RUNNING (current status: OPEN). |
| WO RUNNING sudah punya hasil | `"wo_number": "WO2026000009"` | `wo_number`: Production order already has a production result. |

## Pengujian jalur sukses (tinker)

Response 201 tidak bisa didapat lewat HTTP dengan data awal, dan data awal tidak boleh diubah (PRD R1, R3). Skrip berikut menguji jalur sukses tanpa menyimpan perubahan:

1. Membuka transaksi database.
2. Menambah WO sementara `WO-TEST-0001` berstatus `RUNNING` (`target_qty` 1000), menyalin produk/mesin/operator dari WO pertama.
3. Mengirim POST lewat HTTP kernel Laravel, sehingga validasi, controller, dan service ikut diuji.
4. Mengirim POST kedua ke WO yang sama untuk menguji aturan satu hasil per WO.
5. Rollback, sehingga WO sementara dan hasilnya hilang.

Jalankan dari root project saat container berjalan (bash/zsh; pengguna fish jalankan `bash` lebih dulu):

```bash
docker compose exec -T app php artisan tinker --execute "$(cat <<'PHP'
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

$post = function (array $body) {
    $request = Request::create('/api/production-results', 'POST', [], [], [], [
        'HTTP_ACCEPT' => 'application/json',
        'CONTENT_TYPE' => 'application/json',
    ], json_encode($body));
    $response = app(Kernel::class)->handle($request);
    echo $response->getStatusCode().' '.$response->getContent().PHP_EOL;
};

echo 'Sebelum: production_result = '.DB::table('production_result')->count().PHP_EOL;

DB::beginTransaction();
try {
    $sample = DB::table('work_order')->first();
    DB::table('work_order')->insert([
        'wo_number' => 'WO-TEST-0001',
        'product_code' => $sample->product_code,
        'machine_code' => $sample->machine_code,
        'employee_no' => $sample->employee_no,
        'shift' => $sample->shift,
        'target_qty' => 1000,
        'plan_start' => '2026-10-01 08:00:00',
        'plan_finish' => '2026-10-01 16:00:00',
        'status' => 'RUNNING',
    ]);

    $body = [
        'wo_number' => 'WO-TEST-0001',
        'actual_start' => '2026-10-01 08:00:00',
        'actual_finish' => '2026-10-01 10:30:00',
        'good_qty' => 950,
        'reject_qty' => 10,
    ];

    echo '[1] POST pertama  -> ';
    $post($body);
    echo '[2] POST kedua    -> ';
    $post($body);
    echo '[3] Status WO     -> '.DB::table('work_order')->where('wo_number', 'WO-TEST-0001')->value('status').PHP_EOL;
} finally {
    DB::rollBack();
}

echo 'Sesudah rollback: production_result = '.DB::table('production_result')->count()
    .', WO-TEST-0001 ada = '.(DB::table('work_order')->where('wo_number', 'WO-TEST-0001')->exists() ? 'ya' : 'tidak').PHP_EOL;
PHP
)"
```

Output yang diharapkan (nilai `id` bisa berbeda):

```text
Sebelum: production_result = 1474
[1] POST pertama  -> 201 {"data":{"id":1475,"wo_number":"WO-TEST-0001","actual_start":"2026-10-01 08:00:00","actual_finish":"2026-10-01 10:30:00","runtime_minutes":150,"good_qty":950,"reject_qty":10,"achievement":95}}
[2] POST kedua    -> 422 {"message":"Production order already has a production result.","errors":{"wo_number":["Production order already has a production result."]}}
[3] Status WO     -> RUNNING
Sesudah rollback: production_result = 1474, WO-TEST-0001 ada = tidak
```

| Baris output | Yang dibuktikan |
|---|---|
| `[1]` 201 | Jalur sukses: `runtime_minutes` 150 (08:00 → 10:30), `achievement` 95 (950 / 1000 × 100) |
| `[2]` 422 | Satu WO maksimal satu hasil (PRD B5) |
| `[3]` RUNNING | Status WO tidak diubah oleh POST (PRD R3) |
| Sesudah rollback | Jumlah baris kembali 1474 dan WO sementara hilang; data awal utuh |

Variasi: ubah nilai di `$body` (mis. `good_qty` -1, `actual_start` di masa depan) atau `status` WO sementara (mis. `OPEN`) untuk melihat penolakan 422 yang sesuai pada WO yang sama.

## Gotchas

- Dengan data awal tidak ada WO yang bisa menerima POST lewat HTTP: semua WO `RUNNING` sudah punya hasil (PRD D5). Ini perilaku yang disengaja.
- Tidak ada constraint UNIQUE pada `production_result.wo_number` (PRD D13). Batasan satu hasil per WO dijaga dengan `lockForUpdate()` pada baris `work_order` di dalam transaksi, sehingga dua request bersamaan tidak bisa sama-sama lolos.
- Skrip tinker tidak menyimpan baris data, tetapi rollback InnoDB tidak mengembalikan counter `AUTO_INCREMENT` `production_result`: setiap kali skrip dijalankan, `id` berikutnya maju satu. Reset dengan `docker compose down -v && docker compose up -d` (import ulang database).
- Skrip memakai `--execute` dengan heredoc bash/zsh; sintaks heredoc tidak didukung fish.
- `before_or_equal:now` dievaluasi pada timezone aplikasi `Asia/Jakarta`.

## Related

- [PRD §7.5.4](../initiate-file/prd.md#754-post-apiproduction-results)
- [API Production Orders](api-production-orders.md)
- [Data Model](../data-model.md)
- [Architecture](../architecture.md)
