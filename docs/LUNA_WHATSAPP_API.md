# Dokumentasi Lengkap Integrasi API Luna AI — SIMASS-PuTI

Dokumen ini merupakan referensi teknis lengkap untuk mengintegrasikan **Luna AI (WhatsApp Bot / Gateway)** dengan backend **SIMASS-PuTI** (modul **e-Ticketing** & **Inventaris**).

---

## 1. Informasi Dasar

| Item | Nilai |
|---|---|
| **Base URL** | `https://<domain-simass-puti>/api/v1/luna` |
| **Format Request & Response** | `application/json` (UTF-8) |
| **Format Tanggal / Waktu** | ISO 8601 (`2026-11-20T08:30:00+07:00`) |
| **Versi API** | `v1` |
| **Controller** | `App\Http\Controllers\Api\V1\LunaTicketApiController` |
| **Middleware** | `luna.api` (`App\Http\Middleware\VerifyLunaApiKey`) |

---

## 2. Konfigurasi Environment (`.env`) & Database Settings

Backend membaca konfigurasi keamanan dari `.env` terlebih dahulu; jika kosong, otomatis mengambil fallback dari tabel `settings` di database.

```dotenv
# Secret Token untuk autentikasi sistem Luna AI (wajib diisi; buat acak, mis. `openssl rand -hex 32`)
LUNA_API_KEY=

# Nomor bot WhatsApp yang diizinkan mengakses API (opsional; jika diisi, `phone` selain nomor ini ditolak 403).
# Nomor pelapor asli dikirim lewat `reporter_phone` dan tidak dicek ke nomor ini.
LUNA_ALLOWED_WA_NUMBER=

# Path direktori Knowledge Base Markdown (opsional; default: storage/app/luna-knowledge-base)
# Dapat diarahkan ke volume/folder bersama jika terpasang di server yang sama dengan repo AI
LUNA_KB_PATH=/path/to/luna-knowledge-base
```

Fallback kunci pada tabel `settings`:
- `luna_api_key`
- `luna_allowed_wa_number`

### Normalisasi Nomor WhatsApp Otomatis
Middleware `VerifyLunaApiKey::normalizeWaPhone()` otomatis menormalisasi seluruh format nomor Indonesia ke format kanonik `628xxxxxxxxxx`:

| Input Nomor | Hasil Normalisasi |
|---|---|
| `081234567890` | `6281234567890` |
| `+62 812-3456-7890` | `6281234567890` |
| `81234567890` | `6281234567890` |
| `6281234567890@s.whatsapp.net` | `6281234567890` |
| `6281234567890@c.us` | `6281234567890` |

---

## 3. Autentikasi & Header Wajib

Setiap request ke `/api/v1/luna/*` **wajib** menyertakan header berikut:

| Header | Wajib | Nilai | Keterangan |
|---|---|---|---|
| `Accept` | Ya | `application/json` | Memastikan error validasi & exception dikembalikan sebagai JSON |
| `Content-Type` | Ya (untuk `POST`) | `application/json` | Format payload request body |
| `Authorization` | Ya* | `Bearer <LUNA_API_KEY>` | Metode autentikasi utama |
| `X-Luna-Api-Key` | Alternatif* | `<LUNA_API_KEY>` | Alternatif jika tidak memakai header `Authorization` |
| `X-Wa-Number` | Opsional | `6281234567890` | Nomor bot, dicek ke `LUNA_ALLOWED_WA_NUMBER` jika `phone` tidak dikirim. Bukan penyaring tiket |

> \* Salah satu dari `Authorization: Bearer ...` atau `X-Luna-Api-Key` wajib dikirim.

---

## 4. Ringkasan Endpoint

| # | Method | Endpoint | Deskripsi Singkat |
|---|---|---|---|
| 1 | `GET` | `/api/v1/luna/catalog` | Ambil katalog kategori layanan, `service_catalogs`, & daftar aset inventaris aktif |
| 2 | `GET` | `/api/v1/luna/tickets` | Ambil maksimal 20 tiket terbaru (bisa filter `reporter_phone` & `status`), atau 100 tiket yang berubah sejak `updated_since` |
| 3 | `POST` | `/api/v1/luna/tickets` | Buat tiket baru dari WhatsApp (mendukung kategori dinamis dari AI, validasi WA, deduplikasi, sync aset) |
| 4 | `GET` | `/api/v1/luna/tickets/{ticket_number}` | Ambil detail status tiket, SLA, teknisi PIC, aset tertaut, & riwayat pesan |
| 5 | `POST` | `/api/v1/luna/tickets/{ticket_number}/reply` | Kirim balasan pesan/klarifikasi dari WhatsApp (otomatis resume SLA) |
| 6 | `POST` | `/api/v1/luna/tickets/{ticket_number}/cancel` | Batalkan tiket dari WhatsApp (hanya saat status masih `open`) |
| 7 | `GET` | `/api/v1/luna/knowledge-base` | Daftar seluruh dokumen Knowledge Base (`LUNA_XX_*.md`), checksum `sha256`, + opsional konten & data sinkronisasi SIMASS |
| 8 | `GET` | `/api/v1/luna/knowledge-base/{document}` | Baca isi lengkap satu dokumen Knowledge Base |
| 9 | `POST` | `/api/v1/luna/knowledge-base` | Tambah (`append`) atau buat baru entri/section pada dokumen Knowledge Base |
| 10 | `PUT` | `/api/v1/luna/knowledge-base/{document}` | Timpa penuh (*upsert/sync*) satu dokumen Knowledge Base dari repo AI eksternal |
| 11 | `DELETE` | `/api/v1/luna/knowledge-base/{document}` | Hapus satu dokumen Knowledge Base |

---

## 5. Skema Objek Standar (`TicketResource`)

Seluruh endpoint tiket mengembalikan struktur objek `data` yang seragam:

```json
{
  "id": 12,
  "ticket_number": "TIC-202611-0012",
  "title": "Proyektor di kelas A301 tidak menampilkan gambar HDMI sama sekali ketika dicolok...",
  "description": "Proyektor di kelas A301 tidak menampilkan gambar HDMI sama sekali ketika dicolok ke laptop.",
  "category": "Fasilitas & Lab IT",
  "service_item": "Proyektor / Perangkat Ruang Kelas",
  "authority": "local",
  "scope": "internal_surabaya",
  "status": {
    "value": "open",
    "label": "Diterima",
    "color": "blue",
    "needs_clarification": false,
    "is_final": false
  },
  "priority": "high",
  "location": "Gedung A R.301",
  "source_channel": "whatsapp_luna",
  "reporter": {
    "id": 4,
    "name": "Admin WhatsApp Luna",
    "phone": "6281234567890"
  },
  "assignee": {
    "id": 5,
    "name": "Rizal Jihadi"
  },
  "inventory_item": {
    "id": 3,
    "asset_code": "INV-PUTI-2026-0099",
    "name": "Proyektor Ruang Kelas A301",
    "condition": "damaged",
    "condition_label": "Rusak / Kendala"
  },
  "cancel_reason": null,
  "transferred_unit": null,
  "status_note": null,
  "central_ticket_ref": null,
  "sla_due_at": "2026-11-20T20:30:00+07:00",
  "sla_paused_at": null,
  "resolved_at": null,
  "created_at": "2026-11-20T08:30:00+07:00",
  "messages": [
    {
      "id": 45,
      "sender_type": "user",
      "sender_name": "Admin WhatsApp Luna",
      "message": "Proyektor di kelas A301 tidak menampilkan gambar HDMI sama sekali ketika dicolok ke laptop.",
      "created_at": "2026-11-20T08:30:00+07:00"
    }
  ]
}
```

### Penjelasan Field `TicketResource`

| Field | Tipe | Nullable | Keterangan |
|---|---|---|---|
| `id` | `integer` | Tidak | ID internal tiket di database |
| `ticket_number` | `string` | Tidak | Nomor unik tiket (contoh: `TIC-202611-0012`) |
| `title` | `string` | Tidak | Judul singkat tiket (otomatis dipotong 80 karakter dari bagian `description` sebelum baris `---` jika tidak dikirim) |
| `description` | `string` | Tidak | Deskripsi lengkap keluhan pelapor |
| `category` | `string` | Tidak | Kategori layanan (contoh: `Jaringan & Internet`, `Fasilitas & Lab IT`, `Lainnya`) |
| `service_item` | `string` | Tidak | Item layanan spesifik dari katalog `Ticket::SERVICES` |
| `authority` | `string` | Tidak | `local` (ditangani PuTI Surabaya) atau `central` (butuh koordinasi pusat) |
| `scope` | `string` | Tidak | `internal_surabaya`, `needs_clarification`, atau `escalated_central` |
| `reporter_phone` | `string` | Ya | Nomor WA pelapor asli (format `62…`). Dipakai Luna untuk bukti kepemilikan tiket |
| `reporter_status` | `string` | Ya | `mahasiswa`, `dosen`, atau `staf` |
| `unit_kerja` | `string` | Ya | Unit kerja / fakultas pelapor |
| `request_type` | `string` | Ya | `PENGADUAN IT` atau `PERMINTAAN LAYANAN` |
| `status.value` | `string` | Tidak | Nilai enum status (`open`, `in_progress`, `finished`, `canceled`, `failed`, `redirected`) |
| `status.label` | `string` | Tidak | Label bahasa Indonesia untuk ditampilkan ke pengguna |
| `status.color` | `string` | Tidak | Warna badge UI (`blue`, `purple`, `emerald`, `gray`, `rose`, `orange`) |
| `status.needs_clarification` | `boolean` | Tidak | `true` jika tiket menunggu balasan klarifikasi dari pelapor (SLA dijeda) |
| `status.is_final` | `boolean` | Tidak | `true` jika tiket berstatus `finished` atau `canceled` (tidak bisa dibalas lagi) |
| `priority` | `string` | Tidak | `low`, `medium`, `high`, atau `urgent` |
| `location` | `string` | Ya | Lokasi kendala (fallback otomatis ke lokasi aset inventaris jika `asset_code` dikirim) |
| `source_channel` | `string` | Tidak | Selalu bernilai `whatsapp_luna` untuk tiket dari API ini |
| `reporter` | `object` | Tidak | Informasi pelapor (`id`, `name`, `phone`) |
| `assignee` | `object` | Ya | Teknisi PIC yang ditugaskan otomatis (`id`, `name`), `null` jika dijeda/dieskalasi |
| `inventory_item` | `object` | Ya | Aset inventaris tertaut (`id`, `asset_code`, `name`, `condition`, `condition_label`) |
| `cancel_reason` | `string` | Ya | Alasan pembatalan (bernilai `user_withdrawn` bila dibatalkan via API) |
| `transferred_unit` | `string` | Ya | Unit tujuan jika tiket dialihkan |
| `status_note` | `string` | Ya | Catatan tambahan status / alasan pembatalan |
| `central_ticket_ref` | `string` | Ya | Nomor referensi tiket pusat jika dieskalasi |
| `sla_due_at` | `string` | Ya | Batas waktu SLA (ISO 8601), `null` jika tiket dijeda sejak awal |
| `sla_paused_at` | `string` | Ya | Waktu mulai SLA dijeda (ISO 8601), `null` jika SLA sedang berjalan |
| `resolved_at` | `string` | Ya | Waktu tiket diselesaikan atau dibatalkan (ISO 8601) |
| `created_at` | `string` | Tidak | Waktu pembuatan tiket (ISO 8601) |
| `updated_at` | `string` | Tidak | Waktu perubahan terakhir (ISO 8601); pakai sebagai `updated_since` berikutnya |
| `messages` | `array` | Kondisional | Hanya muncul pada endpoint `POST /tickets`, `GET /tickets/{ticket_number}`, `POST /reply`, dan `POST /cancel` (tidak termasuk catatan internal staf). `sender_type`: `user`, `staff`, `ai_luna`, atau `system`. |

---

## 6. Referensi Enum & Aturan Bisnis

### 6.1. Status Tiket (`TicketStatus`)

| `value` | `label` | `color` | `is_final` | Perilaku SLA | Keterangan |
|---|---|---|---|---|---|
| `open` | Diterima | `blue` | `false` | Berjalan / Dijeda jika `needs_clarification` | Tiket baru masuk, masih bisa dibatalkan via WhatsApp |
| `in_progress` | Sedang Dikerjakan | `purple` | `false` | Berjalan | Teknisi sedang menangani kendala |
| `finished` | Selesai | `emerald` | `true` | Berhenti | Tiket selesai ditangani |
| `canceled` | Dibatalkan | `gray` | `true` | Berhenti | Tiket dibatalkan |
| `failed` | Gagal Ditangani | `rose` | `false` | Berhenti | Penanganan gagal, masih bisa dibuka kembali oleh staf |
| `redirected` | Dialihkan ke Pusat | `orange` | `false` | Dijeda | Memerlukan koordinasi sistem pusat Telkom University |

### 6.2. Prioritas & Target SLA Otomatis

| `priority` | Target SLA (`sla_due_at`) |
|---|---|
| `urgent` | +4 jam sejak tiket aktif |
| `high` | +12 jam sejak tiket aktif |
| `medium` *(default)* | +24 jam sejak tiket aktif |
| `low` | +48 jam sejak tiket aktif |

### 6.3. Logika Otomatis & Proteksi Input WhatsApp (`POST /tickets`)
1. **Validasi Nomor & Grup WA**: Nomor dengan sufiks grup (`@g.us`) atau di luar panjang 9–15 digit setelah normalisasi otomatis ditolak dengan `422` beserta `luna_reply`.
2. **Privacy Guardrail**: Pesan yang memuat **nilai** kata sandi/PIN (`password: rahasia`, `password saya Abc123`) otomatis ditolak; keluhan seperti `lupa password igracias` tetap diterima. Penolakan memakai `422` agar kredensial tidak pernah tersimpan mentah di database.
3. **Deduplikasi Webhook (60 Detik)**: Jika pesan `description` yang identik dari nomor pelapor (`reporter_phone`, atau `phone` jika kosong) yang sama masuk lagi dalam 60 detik terakhir (akibat *retry* gateway WA), API mengembalikan `200 OK` dengan `"duplicate": true` dan tiket yang sudah ada tanpa membuat tiket ganda.
4. **Klasifikasi Kategori Dinamis dari AI**: Jika AI mengirimkan `category`, `service_item`, dan opsional `authority` (`local`/`central`), backend **langsung memakai klasifikasi dari payload AI** (sepenuhnya mengikuti Knowledge Base AI seperti `LUNA_03_KategoriLayanan.md`). Fallback `LunaAiService::suggestService()` hanya dipakai bila `category` atau `service_item` tidak diisi.
5. **Resolusi SLA**: Mencari `sla_resolution_hours` pada tabel `service_catalogs` berdasarkan `service_item`; jika tidak ada, memakai target SLA berdasarkan `priority` (mendukung huruf besar/kecil & alias bahasa Indonesia: `rendah`/`sedang`/`tinggi`/`darurat`).
6. **Evaluasi Ambiguitas**: Jika deskripsi terlalu pendek (< 20 karakter / kata generik seperti `"wifi error"`, `"rusak"`) atau layanan fisik (`authority = local`) tidak menyertakan `location`, maka `scope = needs_clarification`, SLA dijeda, dan `luna_reply` berisi pertanyaan klarifikasi. Panjang dihitung dari `description` lengkap, termasuk blok sesudah `---`.
7. **Sinkronisasi Inventaris & Warning**: `asset_code` dicocokkan secara *case-insensitive*. Jika kode aset tidak ditemukan di database, tiket **tetap dibuat** namun response menyertakan array `warnings` agar bot dapat memberi tahu user.

---

## 7. Detail Setiap Endpoint

---

### 7.1. Ambil Katalog Layanan & Aset Inventaris

`GET /api/v1/luna/catalog`

Mengambil seluruh kategori layanan (`Ticket::SERVICES`) dan daftar aset inventaris yang masih aktif (`condition != retired`) untuk digunakan sebagai konteks/pilihan pada Luna AI.

#### Contoh Request (cURL)
```bash
curl -X GET "https://<domain>/api/v1/luna/catalog" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <LUNA_API_KEY>"
```

#### Contoh Response — `200 OK`
```json
{
  "success": true,
  "services": {
    "Jaringan & Internet": {
      "Wi-Fi Kampus (Eduroam / TelU-Guest)": {
        "authority": "local",
        "icon": "wifi"
      },
      "Jaringan Kabel LAN Gedung / Ruangan": {
        "authority": "local",
        "icon": "cable"
      }
    },
    "Akun & Akses Terpadu": {
      "Akun iGracias & Single Sign-On (SSO)": {
        "authority": "central",
        "icon": "key-round"
      },
      "Email Kampus & Lisensi Microsoft 365": {
        "authority": "central",
        "icon": "mail"
      }
    },
    "Fasilitas & Lab IT": {
      "Komputer Laboratorium / PC Kerja": {
        "authority": "local",
        "icon": "monitor"
      },
      "Proyektor / Perangkat Ruang Kelas": {
        "authority": "local",
        "icon": "projector"
      }
    }
  },
  "inventory_items": [
    {
      "id": 1,
      "asset_code": "INV-PUTI-2026-0001",
      "name": "PC Lab Multimedia Workstation #01",
      "category": "Komputer & PC Lab",
      "location_detail": "Lab Komputer 204 Multimedia",
      "condition": "good"
    },
    {
      "id": 3,
      "asset_code": "INV-PUTI-2026-0003",
      "name": "Proyektor Ruang Sidang Utama",
      "category": "Proyektor & AV",
      "location_detail": "Ruang Sidang Senat Lt. 1",
      "condition": "good"
    }
  ]
}
```

---

### 7.2. Daftar Tiket Terbaru

`GET /api/v1/luna/tickets`

Mengembalikan maksimal **20 tiket terbaru** diurutkan dari yang paling baru (`latest()`). Dengan `updated_since`: maksimal **100 tiket** yang berubah sejak waktu itu, diurutkan dari perubahan terlama; lanjutkan dengan `updated_at` tiket terakhir.

#### Query Parameters

| Parameter | Tipe | Wajib | Keterangan |
|---|---|---|---|
| `reporter_phone` | `string` | Opsional | Filter tiket berdasarkan nomor WA pelapor asli (dinormalisasi otomatis). Jika kosong, mengembalikan tiket dari kanal `whatsapp_luna`. |
| `status` | `string` | Opsional | Filter berdasarkan status (`open`, `in_progress`, `finished`, `canceled`, `failed`, `redirected`). |
| `updated_since` | `string` | Opsional | ISO 8601 (di-*URL-encode*). Tanpa offset dianggap WIB. Nilai tidak valid → `422`. |

#### Contoh Request (cURL)
```bash
curl -X GET "https://<domain>/api/v1/luna/tickets?reporter_phone=081299998888&status=open" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <LUNA_API_KEY>"
```

#### Contoh Response — `200 OK`
```json
{
  "success": true,
  "data": [
    {
      "id": 12,
      "ticket_number": "TIC-202611-0012",
      "title": "Proyektor di kelas A301 tidak menampilkan gambar HDMI sama sekali...",
      "description": "Proyektor di kelas A301 tidak menampilkan gambar HDMI sama sekali ketika dicolok ke laptop.",
      "category": "Fasilitas & Lab IT",
      "service_item": "Proyektor / Perangkat Ruang Kelas",
      "authority": "local",
      "scope": "internal_surabaya",
      "status": {
        "value": "open",
        "label": "Diterima",
        "color": "blue",
        "needs_clarification": false,
        "is_final": false
      },
      "priority": "high",
      "location": "Gedung A R.301",
      "source_channel": "whatsapp_luna",
      "reporter": {
        "id": 4,
        "name": "Admin WhatsApp Luna",
        "phone": "6281234567890"
      },
      "assignee": {
        "id": 5,
        "name": "Rizal Jihadi"
      },
      "inventory_item": {
        "id": 3,
        "asset_code": "INV-PUTI-2026-0099",
        "name": "Proyektor Ruang Kelas A301",
        "condition": "damaged",
        "condition_label": "Rusak / Kendala"
      },
      "cancel_reason": null,
      "transferred_unit": null,
      "status_note": null,
      "central_ticket_ref": null,
      "sla_due_at": "2026-11-20T20:30:00+07:00",
      "sla_paused_at": null,
      "resolved_at": null,
      "created_at": "2026-11-20T08:30:00+07:00"
    }
  ]
}
```

---

### 7.3. Buat Tiket Baru dari WhatsApp

`POST /api/v1/luna/tickets`

Membuat tiket baru dari percakapan WhatsApp, menjalankan inferensi layanan Luna AI, mengevaluasi kelengkapan informasi, menugaskan teknisi otomatis, serta menyinkronkan aset inventaris jika disertakan.

#### Request Body (JSON)

| Field | Tipe | Wajib | Validasi | Keterangan |
|---|---|---|---|---|
| `phone` | `string` | **Ya** | `max:80` | Nomor WhatsApp bot (harus sama dengan `LUNA_ALLOWED_WA_NUMBER` jika diisi; mendukung `08...`, `+62...`, atau JID `@s.whatsapp.net`) |
| `reporter_phone` | `string` | Opsional | `max:80` | Nomor WA pelapor asli. Dinormalisasi ke `62…`, **tidak** dicocokkan dengan `LUNA_ALLOWED_WA_NUMBER`. Jika kosong, memakai `phone` |
| `reporter_status` | `string` | Opsional | `in:mahasiswa,dosen,staf` | Status pelapor |
| `unit_kerja` | `string` | Opsional | `max:150` | Unit kerja / fakultas pelapor |
| `request_type` | `string` | Opsional | `in:PENGADUAN IT,PERMINTAAN LAYANAN` | Jenis laporan |
| `external_id` | `string` | Opsional | `max:100` | Kunci idempotensi (alternatif header `Idempotency-Key`) |
| `description` | `string` | **Ya** | `min:3`, `max:4000` | Deskripsi kendala dari pelapor |
| `reporter_name` | `string` | Opsional | `max:150` | Nama pelapor yang ditampilkan di SIMASS. Semua tiket WhatsApp menumpang satu akun terkunci "Pelapor WhatsApp (Luna)"; nomor HP dan NIM/NIP tidak dicocokkan ke akun |
| `title` | `string` | Opsional | `max:255` | Judul tiket (jika kosong, otomatis diambil 80 karakter pertama `description` sebelum baris `---`) |
| `category` | `string` | Opsional | `max:100` | Kategori layanan (auto-detect via Luna AI jika kosong) |
| `service_item` | `string` | Opsional | `max:150` | Item layanan spesifik (auto-detect via Luna AI jika kosong) |
| `location` | `string` | Opsional | `max:255` | Lokasi gedung/lantai/ruangan (otomatis memakai lokasi aset jika `asset_code` valid) |
| `priority` | `string` | Opsional | `in:low,medium,high,urgent` | Prioritas tiket (default: `medium`) |
| `asset_code` | `string` | Opsional | `max:50` | Kode aset inventaris (contoh: `INV-PUTI-2026-0099`) |
| `inventory_item_id` | `integer` | Opsional | `exists:inventory_items,id` | ID internal aset inventaris (diprioritaskan di atas `asset_code` jika keduanya dikirim) |

#### Idempotensi & Blok Metadata

- Kirim header `Idempotency-Key: <id unik>` (atau field `external_id`). Jika key yang sama datang lagi dalam **24 jam**, API mengembalikan `200 OK` dengan `"duplicate": true` dan tiket yang sudah ada tanpa membuat tiket baru. Permintaan bersamaan dengan key yang sama yang masih diproses mendapat `409 Conflict`.
- Data tambahan boleh ditempel di akhir `description` setelah baris kosong dan `---`. Bagian ini tidak dipakai untuk judul otomatis maupun deteksi layanan.

#### Contoh Request (cURL)
```bash
curl -X POST "https://<domain>/api/v1/luna/tickets" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <LUNA_API_KEY>" \
  -d '{
    "phone": "+62 812-3456-7890@s.whatsapp.net",
    "reporter_phone": "081299998888",
    "reporter_name": "Budi Santoso",
    "description": "Proyektor di kelas A301 tidak menampilkan gambar HDMI sama sekali ketika dicolok ke laptop.",
    "location": "Gedung A Lt. 3 R.301",
    "asset_code": "INV-PUTI-2026-0099",
    "priority": "high"
  }'
```

#### Contoh Response Skenario A (Informasi Lengkap & Aset Tertaut) — `201 Created`
```json
{
  "success": true,
  "message": "Tiket TIC-202611-0012 berhasil dibuat melalui WhatsApp Luna AI.",
  "luna_reply": "Halo! Laporan Anda melalui WhatsApp telah diterima oleh **Luna AI PuTI Surabaya** dengan nomor tiket **TIC-202611-0012** (Status: **Diterima**).",
  "data": {
    "id": 12,
    "ticket_number": "TIC-202611-0012",
    "title": "Proyektor di kelas A301 tidak menampilkan gambar HDMI sama sekali ketika dicolok...",
    "description": "Proyektor di kelas A301 tidak menampilkan gambar HDMI sama sekali ketika dicolok ke laptop.",
    "category": "Fasilitas & Lab IT",
    "service_item": "Proyektor / Perangkat Ruang Kelas",
    "authority": "local",
    "scope": "internal_surabaya",
    "status": {
      "value": "open",
      "label": "Diterima",
      "color": "blue",
      "needs_clarification": false,
      "is_final": false
    },
    "priority": "high",
    "location": "Gedung A Lt. 3 R.301",
    "source_channel": "whatsapp_luna",
    "reporter": {
      "id": 4,
      "name": "Budi Santoso",
      "phone": "6281234567890"
    },
    "assignee": {
      "id": 5,
      "name": "Rizal Jihadi"
    },
    "inventory_item": {
      "id": 3,
      "asset_code": "INV-PUTI-2026-0099",
      "name": "Proyektor Ruang Kelas A301",
      "condition": "damaged",
      "condition_label": "Rusak / Kendala"
    },
    "cancel_reason": null,
    "transferred_unit": null,
    "status_note": null,
    "central_ticket_ref": null,
    "sla_due_at": "2026-11-20T20:30:00+07:00",
    "sla_paused_at": null,
    "resolved_at": null,
    "created_at": "2026-11-20T08:30:00+07:00",
    "messages": [
      {
        "id": 45,
        "sender_type": "user",
        "sender_name": "Admin WhatsApp Luna",
        "message": "Proyektor di kelas A301 tidak menampilkan gambar HDMI sama sekali ketika dicolok ke laptop.",
        "created_at": "2026-11-20T08:30:00+07:00"
      }
    ]
  }
}
```

#### Contoh Response Skenario B (Pesan Ambigu / Tanpa Lokasi $\rightarrow$ `needs_clarification`) — `201 Created`
Jika user hanya mengirim `{"phone": "081234567890", "description": "wifi error"}`:
```json
{
  "success": true,
  "message": "Tiket TIC-202611-0013 berhasil dibuat melalui WhatsApp Luna AI.",
  "luna_reply": "Halo! Saya **Luna AI** dari Helpdesk PuTI. Terima kasih telah melapor. 😊\n\nAgar teknisi PuTI kami dapat segera meluncur dan menangani kendala Anda, mohon bantu melengkapi:\n1. **Lokasi spesifik:** Nama Gedung, Lantai, dan Nomor Ruangan.\n2. **Detail kendala:** Gejala kerusakan atau pesan error yang muncul.\n\nAnda dapat langsung membalas pesan ini di bawah. Terima kasih!",
  "data": {
    "id": 13,
    "ticket_number": "TIC-202611-0013",
    "title": "wifi error",
    "description": "wifi error",
    "category": "Jaringan & Internet",
    "service_item": "Wi-Fi Kampus (Eduroam / TelU-Guest)",
    "authority": "local",
    "scope": "needs_clarification",
    "status": {
      "value": "open",
      "label": "Diterima",
      "color": "blue",
      "needs_clarification": true,
      "is_final": false
    },
    "priority": "medium",
    "location": null,
    "source_channel": "whatsapp_luna",
    "reporter": {
      "id": 4,
      "name": "Admin WhatsApp Luna",
      "phone": "6281234567890"
    },
    "assignee": null,
    "inventory_item": null,
    "cancel_reason": null,
    "transferred_unit": null,
    "status_note": null,
    "central_ticket_ref": null,
    "sla_due_at": null,
    "sla_paused_at": "2026-11-20T08:35:00+07:00",
    "resolved_at": null,
    "created_at": "2026-11-20T08:35:00+07:00",
    "messages": [
      {
        "id": 46,
        "sender_type": "user",
        "sender_name": "Admin WhatsApp Luna",
        "message": "wifi error",
        "created_at": "2026-11-20T08:35:00+07:00"
      },
      {
        "id": 47,
        "sender_type": "ai_luna",
        "sender_name": "Luna AI",
        "message": "Halo! Saya **Luna AI** dari Helpdesk PuTI...",
        "created_at": "2026-11-20T08:35:00+07:00"
      }
    ]
  }
}
```

#### Contoh Response Skenario C (Layanan Pusat / `authority = central`) — `201 Created`
Jika keluhan terkait SSO / iGracias / M365:
- `scope`: `"escalated_central"`
- `status.value`: `"redirected"` (`"Dialihkan ke Pusat"`)
- `sla_paused_at`: diisi waktu saat ini (SLA dijeda untuk koordinasi pusat)
- `luna_reply`: berisi pesan bahwa laporan sedang dikoordinasikan bersama tim sistem terpusat Telkom University.

---

### 7.4. Cek Detail & Riwayat Tiket

`GET /api/v1/luna/tickets/{ticket_number}`

Mengambil detail lengkap satu tiket beserta seluruh riwayat percakapan publik (`messages`).

#### Path Parameters

| Parameter | Tipe | Wajib | Keterangan |
|---|---|---|---|
| `ticket_number` | `string` | **Ya** | Nomor tiket (contoh: `TIC-202611-0012`) |

#### Contoh Request (cURL)
```bash
curl -X GET "https://<domain>/api/v1/luna/tickets/TIC-202611-0012" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <LUNA_API_KEY>" \
  -H "X-Wa-Number: 6281234567890"
```

#### Contoh Response — `200 OK`
```json
{
  "success": true,
  "data": {
    "id": 12,
    "ticket_number": "TIC-202611-0012",
    "title": "Proyektor di kelas A301 tidak menampilkan gambar HDMI sama sekali...",
    "description": "Proyektor di kelas A301 tidak menampilkan gambar HDMI sama sekali ketika dicolok ke laptop.",
    "category": "Fasilitas & Lab IT",
    "service_item": "Proyektor / Perangkat Ruang Kelas",
    "authority": "local",
    "scope": "internal_surabaya",
    "status": {
      "value": "in_progress",
      "label": "Sedang Dikerjakan",
      "color": "purple",
      "needs_clarification": false,
      "is_final": false
    },
    "priority": "high",
    "location": "Gedung A Lt. 3 R.301",
    "source_channel": "whatsapp_luna",
    "reporter": {
      "id": 4,
      "name": "Budi Santoso",
      "phone": "6281234567890"
    },
    "assignee": {
      "id": 5,
      "name": "Rizal Jihadi"
    },
    "inventory_item": {
      "id": 3,
      "asset_code": "INV-PUTI-2026-0099",
      "name": "Proyektor Ruang Kelas A301",
      "condition": "damaged",
      "condition_label": "Rusak / Kendala"
    },
    "cancel_reason": null,
    "transferred_unit": null,
    "status_note": null,
    "central_ticket_ref": null,
    "sla_due_at": "2026-11-20T20:30:00+07:00",
    "sla_paused_at": null,
    "resolved_at": null,
    "created_at": "2026-11-20T08:30:00+07:00",
    "messages": [
      {
        "id": 45,
        "sender_type": "user",
        "sender_name": "Admin WhatsApp Luna",
        "message": "Proyektor di kelas A301 tidak menampilkan gambar HDMI sama sekali ketika dicolok ke laptop.",
        "created_at": "2026-11-20T08:30:00+07:00"
      }
    ]
  }
}
```

#### Contoh Response — `404 Not Found`
```json
{
  "success": false,
  "message": "Tiket TIC-202611-9999 tidak ditemukan."
}
```

---

### 7.5. Kirim Balasan / Klarifikasi Tiket

`POST /api/v1/luna/tickets/{ticket_number}/reply`

Menambahkan pesan balasan dari pelapor WhatsApp ke thread tiket. Jika tiket sebelumnya dalam kondisi `needs_clarification`:
- SLA otomatis dilanjutkan (`sla_paused_at` menjadi `null`, `sla_due_at` diperbarui/ditambahkan durasi jeda).
- `scope` berubah menjadi `internal_surabaya`.
- Teknisi PIC otomatis ditugaskan jika belum ada.
- Pesan sistem otomatis dicatat pada thread.

#### Request Body (JSON)

| Field | Tipe | Wajib | Validasi | Keterangan |
|---|---|---|---|---|
| `phone` | `string` | **Ya** | `max:50` | Nomor WhatsApp pengirim |
| `message` | `string` | **Ya** | `min:2` | Isi pesan balasan / klarifikasi |

#### Contoh Request (cURL)
```bash
curl -X POST "https://<domain>/api/v1/luna/tickets/TIC-202611-0013/reply" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <LUNA_API_KEY>" \
  -d '{
    "phone": "081234567890",
    "message": "Lokasi di Gedung A Lantai 3 R.305, tidak bisa dapat IP address."
  }'
```

#### Contoh Response — `200 OK`
```json
{
  "success": true,
  "message": "Balasan WhatsApp berhasil ditambahkan ke tiket.",
  "data": {
    "id": 13,
    "ticket_number": "TIC-202611-0013",
    "title": "wifi error",
    "description": "wifi error",
    "category": "Jaringan & Internet",
    "service_item": "Wi-Fi Kampus (Eduroam / TelU-Guest)",
    "authority": "local",
    "scope": "internal_surabaya",
    "status": {
      "value": "open",
      "label": "Diterima",
      "color": "blue",
      "needs_clarification": false,
      "is_final": false
    },
    "priority": "medium",
    "location": null,
    "source_channel": "whatsapp_luna",
    "reporter": {
      "id": 4,
      "name": "Admin WhatsApp Luna",
      "phone": "6281234567890"
    },
    "assignee": {
      "id": 5,
      "name": "Rizal Jihadi"
    },
    "inventory_item": null,
    "cancel_reason": null,
    "transferred_unit": null,
    "status_note": null,
    "central_ticket_ref": null,
    "sla_due_at": "2026-11-21T08:40:00+07:00",
    "sla_paused_at": null,
    "resolved_at": null,
    "created_at": "2026-11-20T08:35:00+07:00",
    "messages": [
      {
        "id": 46,
        "sender_type": "user",
        "sender_name": "Admin WhatsApp Luna",
        "message": "wifi error",
        "created_at": "2026-11-20T08:35:00+07:00"
      },
      {
        "id": 47,
        "sender_type": "ai_luna",
        "sender_name": "Luna AI",
        "message": "Halo! Saya **Luna AI** dari Helpdesk PuTI...",
        "created_at": "2026-11-20T08:35:00+07:00"
      },
      {
        "id": 48,
        "sender_type": "user",
        "sender_name": "Admin WhatsApp Luna",
        "message": "Lokasi di Gedung A Lantai 3 R.305, tidak bisa dapat IP address.",
        "created_at": "2026-11-20T08:40:00+07:00"
      },
      {
        "id": 49,
        "sender_type": "system",
        "sender_name": "Sistem",
        "message": "ℹ️ **Sistem (WhatsApp):** Pelapor telah memberikan informasi tambahan. Tiket aktif kembali dan SLA dilanjutkan.",
        "created_at": "2026-11-20T08:40:00+07:00"
      }
    ]
  }
}
```

#### Contoh Response Error (Tiket Sudah Selesai/Dibatalkan) — `422 Unprocessable Entity`
```json
{
  "success": false,
  "message": "Tiket ini sudah selesai atau dibatalkan dan tidak dapat menerima balasan baru."
}
```

---

### 7.6. Batalkan Tiket

`POST /api/v1/luna/tickets/{ticket_number}/cancel`

Membatalkan tiket langsung dari WhatsApp. Hanya diizinkan selama tiket masih berstatus **`open`**. Jika tiket tertaut dengan aset inventaris, status aset ikut disinkronkan kembali.

#### Request Body (JSON)

| Field | Tipe | Wajib | Validasi | Keterangan |
|---|---|---|---|---|
| `phone` | `string` | **Ya** | `max:50` | Nomor WhatsApp pelapor |
| `status_note` | `string` | Opsional | `max:500` | Alasan pembatalan (default: `"Dibatalkan melalui WhatsApp Luna AI."`) |

#### Contoh Request (cURL)
```bash
curl -X POST "https://<domain>/api/v1/luna/tickets/TIC-202611-0013/cancel" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <LUNA_API_KEY>" \
  -d '{
    "phone": "081234567890",
    "status_note": "Wi-Fi sudah kembali normal setelah reconnect."
  }'
```

#### Contoh Response — `200 OK`
```json
{
  "success": true,
  "message": "Tiket TIC-202611-0013 berhasil dibatalkan.",
  "data": {
    "id": 13,
    "ticket_number": "TIC-202611-0013",
    "title": "wifi error",
    "description": "wifi error",
    "category": "Jaringan & Internet",
    "service_item": "Wi-Fi Kampus (Eduroam / TelU-Guest)",
    "authority": "local",
    "scope": "internal_surabaya",
    "status": {
      "value": "canceled",
      "label": "Dibatalkan",
      "color": "gray",
      "needs_clarification": false,
      "is_final": true
    },
    "priority": "medium",
    "location": null,
    "source_channel": "whatsapp_luna",
    "reporter": {
      "id": 4,
      "name": "Admin WhatsApp Luna",
      "phone": "6281234567890"
    },
    "assignee": {
      "id": 5,
      "name": "Rizal Jihadi"
    },
    "inventory_item": null,
    "cancel_reason": "user_withdrawn",
    "transferred_unit": null,
    "status_note": "Wi-Fi sudah kembali normal setelah reconnect.",
    "central_ticket_ref": null,
    "sla_due_at": "2026-11-21T08:40:00+07:00",
    "sla_paused_at": null,
    "resolved_at": "2026-11-20T08:45:00+07:00",
    "created_at": "2026-11-20T08:35:00+07:00",
    "messages": [
      {
        "id": 50,
        "sender_type": "system",
        "sender_name": "Sistem",
        "message": "🚫 **Tiket Dibatalkan (WhatsApp):** Tiket dibatalkan melalui Luna AI WhatsApp.",
        "created_at": "2026-11-20T08:45:00+07:00"
      }
    ]
  }
}
```

#### Contoh Response Error (Status Bukan `open`) — `422 Unprocessable Entity`
```json
{
  "success": false,
  "message": "Tiket hanya dapat dibatalkan selama berstatus Diterima (Open).",
  "luna_reply": "Tiket **TIC-202611-0013** tidak dapat dibatalkan karena sudah berstatus **Sedang Dikerjakan**."
}
```

---

## 8. Endpoint Knowledge Base (Integrasi Repo AI Eksternal)

Endpoint ini dirancang untuk menghubungkan **SIMASS-PuTI** dengan **repositori AI Model / Knowledge Base eksternal** yang menggunakan struktur dokumen standar:
- `LUNA_01_Identitas.md`
- `LUNA_02_AlurLayanan.md`
- `LUNA_03_KategoriLayanan.md`
- `LUNA_04_Keamanan.md`
- `LUNA_06_Arsitektur.md`

> **Keamanan Nama File:** Hanya nama file dengan pola `^LUNA_\d{2}_[A-Za-z0-9_-]+(\.md)?$` yang diizinkan untuk mencegah *path traversal*.

---

### 8.1. Daftar Semua Dokumen Knowledge Base

`GET /api/v1/luna/knowledge-base`

| Query Parameter | Tipe | Default | Keterangan |
|---|---|---|---|
| `include_content` | `boolean` (`1`/`0`) | `false` | Jika `true`, menyertakan isi penuh markdown tiap dokumen |
| `include_sync_data` | `boolean` (`1`/`0`) | `false` | Jika `true`, menyertakan data live `ticket_services`, `service_catalogs`, dan `inventory_categories` dari database SIMASS untuk kebutuhan sinkronisasi ke `LUNA_03_KategoriLayanan.md` |

#### Contoh Request (cURL)
```bash
curl -X GET "https://<domain>/api/v1/luna/knowledge-base?include_content=1&include_sync_data=1" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <LUNA_API_KEY>"
```

#### Contoh Response — `200 OK`
```json
{
  "success": true,
  "kb_path_configured": true,
  "standard_documents": [
    "LUNA_01_Identitas.md",
    "LUNA_02_AlurLayanan.md",
    "LUNA_03_KategoriLayanan.md",
    "LUNA_04_Keamanan.md",
    "LUNA_06_Arsitektur.md"
  ],
  "count": 1,
  "data": [
    {
      "filename": "LUNA_03_KategoriLayanan.md",
      "key": "LUNA_03_KategoriLayanan",
      "is_standard": true,
      "size_bytes": 124,
      "sha256": "8b1a9953c4611296a827abf8c47804d7...",
      "updated_at": "2026-11-20T09:20:00+07:00",
      "content": "## Jaringan & Internet\n\n- Wi-Fi Kampus (local)\n"
    }
  ],
  "sync_data": {
    "ticket_services": { "...": "..." },
    "service_catalogs": [
      { "id": 1, "category": "Jaringan & Internet", "name": "Wi-Fi Kampus", "sla_resolution_hours": 12 }
    ],
    "inventory_categories": [
      { "id": 1, "code": "PC", "name": "Komputer & PC Lab", "description": "..." }
    ]
  }
}
```

---

### 8.2. Baca Satu Dokumen Knowledge Base

`GET /api/v1/luna/knowledge-base/{document}`

Parameter `{document}` dapat dikirim dengan atau tanpa `.md` (contoh: `LUNA_03_KategoriLayanan` atau `LUNA_03_KategoriLayanan.md`).

```bash
curl -X GET "https://<domain>/api/v1/luna/knowledge-base/LUNA_03_KategoriLayanan" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <LUNA_API_KEY>"
```

---

### 8.3. Tambah / Append Data ke Knowledge Base

`POST /api/v1/luna/knowledge-base`

Menambahkan bagian baru (`mode: "append"`, default) atau menimpa isi dokumen (`mode: "replace"`). Jika file belum ada, otomatis dibuat (`201 Created`).

#### Request Body (JSON)

| Field | Tipe | Wajib | Validasi | Keterangan |
|---|---|---|---|---|
| `document` | `string` | **Ya** | Format `LUNA_XX_Nama(.md)` | Nama dokumen target (contoh: `LUNA_03_KategoriLayanan.md`) |
| `section_title` | `string` | Opsional | `max:200` | Jika diisi, otomatis diformat menjadi heading `## <section_title>` |
| `content` | `string` | **Ya** | `min:3`, `max:100000` | Konten markdown yang ditambahkan |
| `mode` | `string` | Opsional | `in:append,replace` | Default: `append` |

#### Contoh Request (cURL)
```bash
curl -X POST "https://<domain>/api/v1/luna/knowledge-base" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <LUNA_API_KEY>" \
  -d '{
    "document": "LUNA_03_KategoriLayanan.md",
    "section_title": "Keamanan & Firewall",
    "content": "- Whitelist Port Server Riset (`authority: local`, prioritas `high`)",
    "mode": "append"
  }'
```

#### Contoh Response — `201 Created` (file baru) / `200 OK` (append ke file lama)
```json
{
  "success": true,
  "message": "Entri baru berhasil ditambahkan ke 'LUNA_03_KategoriLayanan.md'.",
  "data": {
    "filename": "LUNA_03_KategoriLayanan.md",
    "key": "LUNA_03_KategoriLayanan",
    "is_standard": true,
    "size_bytes": 185,
    "sha256": "a3f5c8e...",
    "updated_at": "2026-11-20T09:25:00+07:00",
    "content": "## Keamanan & Firewall\n\n- Whitelist Port Server Riset (`authority: local`, prioritas `high`)\n"
  }
}
```

---

### 8.4. Sinkronisasi Penuh (*Upsert*) Dokumen

`PUT /api/v1/luna/knowledge-base/{document}`

Cocok untuk push/sync otomatis dari repo AI eksternal (misal lewat GitHub Action / webhook / script sync).

```bash
curl -X PUT "https://<domain>/api/v1/luna/knowledge-base/LUNA_01_Identitas.md" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <LUNA_API_KEY>" \
  -d '{
    "content": "# LUNA 01 — Identitas\nSaya adalah Luna AI, asisten layanan Helpdesk PuTI Telkom University Surabaya."
  }'
```

---

### 8.5. Hapus Dokumen Knowledge Base

`DELETE /api/v1/luna/knowledge-base/{document}`

```bash
curl -X DELETE "https://<domain>/api/v1/luna/knowledge-base/LUNA_01_Identitas.md" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <LUNA_API_KEY>"
```

---

## 9. Daftar Kode Status HTTP & Format Error

| HTTP Code | Arti | Contoh Response JSON |
|---|---|---|
| `200 OK` | Request `GET` / `reply` / `cancel` / `PUT` / duplikat webhook (`duplicate: true`) berhasil | `{"success": true, "data": ...}` |
| `201 Created` | Tiket baru atau dokumen KB baru berhasil dibuat | `{"success": true, "message": "...", "luna_reply": "...", "data": ...}` |
| `401 Unauthorized` | API Key tidak dikirim, salah, atau belum dikonfigurasi di server | `{"success": false, "message": "Unauthorized: Invalid or missing Luna AI API key."}` |
| `403 Forbidden` | Nomor WhatsApp tidak cocok dengan `LUNA_ALLOWED_WA_NUMBER` | `{"success": false, "message": "Forbidden: Nomor WhatsApp ini tidak diizinkan untuk mengakses layanan tiket Luna AI."}` |
| `404 Not Found` | Nomor tiket atau dokumen KB tidak ditemukan | `{"success": false, "message": "Tiket TIC-202611-9999 tidak ditemukan.", "luna_reply": "..."}` |
| `409 Conflict` | Permintaan dengan `Idempotency-Key` yang sama masih diproses | `{"success": false, "message": "..."}` |
| `422 Unprocessable Entity` | Error validasi input WA (grup `@g.us`, password terdeteksi, field kosong, nama file tidak valid, atau status tiket tidak memenuhi syarat) | Lihat contoh di bawah |
| `500 Internal Server Error` | Kesalahan tak terduga di server (detail tidak ditampilkan). Semua error di `/api/v1/luna/*` selalu JSON, tidak pernah HTML | `{"success": false, "message": "Terjadi kesalahan pada server SIMASS."}` |

#### Contoh Format Error Validasi Konsisten (`422 Unprocessable Entity`)
```json
{
  "success": false,
  "message": "Deskripsi kendala terlalu singkat (minimal 3 karakter).",
  "luna_reply": "Maaf, permintaan belum dapat diproses: Deskripsi kendala terlalu singkat (minimal 3 karakter).",
  "errors": {
    "description": [
      "Deskripsi kendala terlalu singkat (minimal 3 karakter)."
    ]
  }
}
```

---

## 10. Alur Integrasi Bot WhatsApp & Repo AI Eksternal

```text
1. [Sync Knowledge Base antara Repo AI <-> SIMASS]
   - Push KB dari Repo AI -> PUT /api/v1/luna/knowledge-base/{LUNA_XX_*.md}
   - Pull Master Data SIMASS -> GET /api/v1/luna/knowledge-base?include_sync_data=1
   - Tambah entri baru -> POST /api/v1/luna/knowledge-base (mode: append)

2. [User Melapor via WhatsApp -> Diproses AI Model menggunakan LUNA_01 s/d LUNA_06]
   AI mengklasifikasikan `category`, `service_item`, `authority`, dan `priority`, lalu kirim:
   POST /api/v1/luna/tickets
   ├─ Kirim field `luna_reply` langsung ke user WhatsApp.
   ├─ Jika ada `warnings` (misal kode aset tidak ditemukan), informasikan ke user.
   └─ Jika `duplicate == true`, abaikan pembuatan sesi duplikat.

3. [Jika `data.status.needs_clarification == true`]
   Saat user membalas pesan klarifikasi:
   POST /api/v1/luna/tickets/{ticket_number}/reply
   └─ SLA otomatis dilanjutkan dan teknisi langsung ditugaskan.

4. [User Bertanya Status / Membatalkan Tiket]
   GET  /api/v1/luna/tickets/{ticket_number}  (case-insensitive, mendukung #TIC-...)
   POST /api/v1/luna/tickets/{ticket_number}/cancel (dengan `status_note`)
```
