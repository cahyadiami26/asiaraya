#!/usr/bin/env node
/**
 * Hasilkan ulang berkas demo dari basis data pratinjau (SQLite).
 *
 *   node tools/preview/gen-dump.mjs
 *
 * Output:
 *   database/demo-data.sql       — INSERT MySQL untuk seluruh tabel (tanpa struktur)
 *   database/demo-preview.sqlite — salinan basis data SQLite pratinjau
 *
 * Skrip ini khusus pengembangan; tidak dipakai pada server produksi.
 */
import { DatabaseSync } from 'node:sqlite';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const DB_FILE = path.join(ROOT, 'storage', 'database.sqlite');
const SQL_OUT = path.join(ROOT, 'database', 'demo-data.sql');
const SQLITE_OUT = path.join(ROOT, 'database', 'demo-preview.sqlite');

if (!fs.existsSync(DB_FILE)) {
    console.error('Basis data pratinjau tidak ditemukan: ' + DB_FILE);
    process.exit(1);
}

const db = new DatabaseSync(DB_FILE);
db.exec('PRAGMA wal_checkpoint(FULL)');

const settingValue = (key, fallback = '') => {
    try {
        const row = db.prepare('SELECT setting_value AS value FROM settings WHERE setting_key = ? LIMIT 1').get(key);
        return row ? String(row.value) : fallback;
    } catch {
        return fallback;
    }
};

// URL aplikasi pratinjau (localhost) diganti dengan domain contoh agar dump
// portabel dan tidak memuat alamat sandbox.
const DEMO_BASE = process.env.DEMO_BASE_URL || 'https://hris.asiaraya.co.id';
const HOST_PATTERNS = ['http://127.0.0.1:8080', 'http://localhost:8080', 'http://localhost'];
const demoLink = (text) => HOST_PATTERNS.reduce((acc, pattern) => acc.split(pattern).join(DEMO_BASE), String(text));

const schemaVersion = settingValue('schema_version', '?');
const appVersion = settingValue('app_version', '');

const tables = db
    .prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name")
    .all()
    .map((row) => row.name);

const quote = (value) => {
    if (value === null || value === undefined) {
        return 'NULL';
    }
    if (typeof value === 'bigint') {
        return value.toString();
    }
    if (typeof value === 'number') {
        return Number.isFinite(value) ? String(value) : 'NULL';
    }
    if (value instanceof Uint8Array) {
        return "X'" + Buffer.from(value).toString('hex') + "'";
    }
    return "'" + String(value).replace(/'/g, "''") + "'";
};

const lines = [];
const stamp = new Intl.DateTimeFormat('sv-SE', {
    timeZone: 'Asia/Jakarta',
    dateStyle: 'short',
    timeStyle: 'short',
}).format(new Date());
let totalRows = 0;

lines.push('-- ---------------------------------------------------------------------------');
lines.push('-- HRIS Asia Raya - dump data demo');
lines.push('-- Dibuat : ' + stamp + ' dari database pratinjau (skema ' + schemaVersion + ')');
if (appVersion !== '') {
    lines.push('-- Versi  : ' + appVersion);
}
lines.push('-- Isi    : seluruh baris data contoh (tanpa struktur tabel)');
lines.push('--');
lines.push('-- MySQL / MariaDB:');
lines.push('--   mysql -u root -p namadb < database/schema.sql');
lines.push('--   mysql -u root -p namadb < database/demo-data.sql');
lines.push('--');
lines.push('-- SQLite: gunakan database/demo-preview.sqlite (salin ke storage/database.sqlite).');
lines.push('--');
lines.push("-- Catatan: pernyataan SET di bawah hanya untuk MySQL. Mode NO_BACKSLASH_ESCAPES");
lines.push("-- dipakai agar kutip tunggal ('') menjadi satu-satunya escape, sehingga isi JSON");
lines.push('-- (mis. snapshot BAST) tersimpan apa adanya. Impor ke database kosong agar tidak');
lines.push('-- bentrok dengan kunci unik.');
lines.push('-- ---------------------------------------------------------------------------');
lines.push('');
lines.push('SET NAMES utf8mb4;');
lines.push("SET SESSION sql_mode = 'NO_BACKSLASH_ESCAPES';");
lines.push('');

for (const table of tables) {
    const rows = db.prepare('SELECT * FROM `' + table + '`').all();
    if (rows.length === 0) {
        continue;
    }
    const columns = Object.keys(rows[0]);
    const columnList = columns.map((column) => '`' + column + '`').join(', ');

    lines.push('');
    lines.push('-- ' + table + ': ' + rows.length + ' baris');
    for (const row of rows) {
        const values = columns
            .map((column) => {
                const value = row[column];
                return quote(typeof value === 'string' ? demoLink(value) : value);
            })
            .join(', ');
        lines.push('INSERT INTO `' + table + '` (' + columnList + ') VALUES (' + values + ');');
    }
    totalRows += rows.length;
}

lines.push('');
lines.push('-- Total: ' + totalRows + ' baris pada ' + tables.length + ' tabel.');
lines.push('');

fs.writeFileSync(SQL_OUT, lines.join('\n'), 'utf8');
db.close();

fs.copyFileSync(DB_FILE, SQLITE_OUT);

// Samakan isi salinan SQLite: ganti URL lokal pada kolom teks.
const copy = new DatabaseSync(SQLITE_OUT);
for (const table of tables) {
    const columns = copy.prepare('PRAGMA table_info(`' + table + '`)').all();
    for (const column of columns) {
        const type = String(column.type || '').toUpperCase();
        if (!/(CHAR|TEXT|CLOB|JSON)/.test(type)) {
            continue;
        }
        for (const pattern of HOST_PATTERNS) {
            copy.prepare(
                'UPDATE `' + table + '` SET `' + column.name + '` = REPLACE(`' + column.name + '`, ?, ?) WHERE `' +
                    column.name + '` LIKE ?'
            ).run(pattern, DEMO_BASE, '%' + pattern + '%');
        }
    }
}
copy.close();

const sizeMb = (fs.statSync(SQL_OUT).size / (1024 * 1024)).toFixed(2);
console.log(
    'OK ' + path.relative(ROOT, SQL_OUT) + ' — ' + totalRows + ' baris / ' + tables.length + ' tabel / ' + sizeMb + ' MB'
);
console.log('OK ' + path.relative(ROOT, SQLITE_OUT) + ' — ' + (fs.statSync(SQLITE_OUT).size / (1024 * 1024)).toFixed(2) + ' MB');
