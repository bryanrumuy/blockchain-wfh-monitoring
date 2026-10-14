'use strict';

const { Contract } = require('fabric-contract-api');
const stringify = require('json-stringify-deterministic');
const sortKeysRecursive = require('sort-keys-recursive');
const crypto = require('crypto');

// ============================================================
// Helper functions (module-level, bukan chaincode transaction)
// ============================================================
function computeHash(blockIndex, previousHash, timestamp, id, employeeName, department, activity, duration, activityType, notes, date, nonce) {
    const content = [
        String(blockIndex), previousHash, timestamp,
        id, employeeName, department, activity,
        String(duration), activityType, notes, date,
        String(nonce)
    ].join('|');
    return crypto.createHash('sha256').update(content).digest('hex');
}

function mineBlock(blockIndex, previousHash, timestamp, id, employeeName, department, activity, duration, activityType, notes, date) {
    let nonce = 0;
    let hash;
    do {
        nonce++;
        hash = computeHash(blockIndex, previousHash, timestamp, id, employeeName, department, activity, duration, activityType, notes, date, nonce);
    } while (!hash.startsWith('000'));
    return { hash, nonce };
}

function hashPassword(password) {
    return crypto.createHash('sha256').update(password).digest('hex');
}

class WFHActivityContract extends Contract {

    // InitLedger: Inisialisasi data awal + user
    async InitLedger(ctx) {
        // Buat user default
        const users = [
            { username: 'admin',    password: 'admin123', role: 'admin', name: 'Administrator' },
            { username: 'employee1',    password: 'user123',  role: 'user',  name: 'Employee 1'         },
            { username: 'employee2',     password: 'user123',  role: 'user',  name: 'Employee 2'          },
            { username: 'employee3',  password: 'user123',  role: 'user',  name: 'Employee 3'       },
            { username: 'employee4',   password: 'user123',  role: 'user',  name: 'Employee 4'        },
            { username: 'employee5', password: 'user123',  role: 'user',  name: 'Employee 5'      },
        ];

        for (const u of users) {
            const userDoc = {
                Username:     u.username,
                PasswordHash: hashPassword(u.password),
                Role:         u.role,
                Name:         u.name,
                DocType:      'user',
            };
            await ctx.stub.putState(
                'USER_' + u.username,
                Buffer.from(stringify(sortKeysRecursive(userDoc)))
            );
            console.log(`User ${u.username} created`);
        }

        // Data aktivitas awal
        const sampleData = [
            { ID: 'act1', EmployeeName: 'Budi Santoso',  Department: 'Engineering', Activity: 'Coding - Feature Authentication',  Duration: 120, ActivityType: 'Productive', Notes: 'Mengerjakan fitur login dan autentikasi JWT',           Date: '2024-01-15', Timestamp: '2024-01-15T08:00:00.000Z' },
            { ID: 'act2', EmployeeName: 'Siti Rahayu',   Department: 'Marketing',   Activity: 'Meeting Online - Tim Marketing',    Duration: 60,  ActivityType: 'Meeting',    Notes: 'Rapat mingguan via Zoom - perencanaan kampanye Q1',   Date: '2024-01-15', Timestamp: '2024-01-15T09:00:00.000Z' },
            { ID: 'act3', EmployeeName: 'Andi Wijaya',   Department: 'Finance',     Activity: 'Analisis Laporan Keuangan Q4',      Duration: 90,  ActivityType: 'Productive', Notes: 'Review dan analisis laporan keuangan kuartal 4',       Date: '2024-01-15', Timestamp: '2024-01-15T08:30:00.000Z' },
            { ID: 'act4', EmployeeName: 'Rina Kusuma',   Department: 'Design',      Activity: 'Desain UI Dashboard Admin',          Duration: 150, ActivityType: 'Productive', Notes: 'Membuat wireframe dan prototype halaman admin',        Date: '2024-01-15', Timestamp: '2024-01-15T10:00:00.000Z' },
            { ID: 'act5', EmployeeName: 'Budi Santoso',  Department: 'Engineering', Activity: 'Code Review & Testing',              Duration: 75,  ActivityType: 'Productive', Notes: 'Review pull request rekan tim dan unit testing',       Date: '2024-01-15', Timestamp: '2024-01-15T13:00:00.000Z' },
            { ID: 'act6', EmployeeName: 'Siti Rahayu',   Department: 'Marketing',   Activity: 'Riset Kompetitor',                   Duration: 90,  ActivityType: 'Productive', Notes: 'Analisis strategi marketing kompetitor untuk Q1',      Date: '2024-01-15', Timestamp: '2024-01-15T13:30:00.000Z' },
        ];

        let previousHash = '0'.repeat(64);
        for (let i = 0; i < sampleData.length; i++) {
            const d = sampleData[i];
            const blockIndex = i + 1;

            const { hash, nonce } = mineBlock(
                blockIndex, previousHash, d.Timestamp,
                d.ID, d.EmployeeName, d.Department, d.Activity,
                d.Duration, d.ActivityType, d.Notes, d.Date
            );

            const activity = {
                ID:           d.ID,
                EmployeeName: d.EmployeeName,
                Department:   d.Department,
                Activity:     d.Activity,
                Duration:     d.Duration,
                ActivityType: d.ActivityType,
                Notes:        d.Notes,
                Date:         d.Date,
                Timestamp:    d.Timestamp,
                BlockIndex:   blockIndex,
                PreviousHash: previousHash,
                Hash:         hash,
                Nonce:        nonce,
                DocType:      'wfh_activity',
            };

            await ctx.stub.putState(d.ID, Buffer.from(stringify(sortKeysRecursive(activity))));
            console.log(`Block ${blockIndex} mined | ID: ${d.ID} | Hash: ${hash.substring(0, 12)}... | Nonce: ${nonce}`);
            previousHash = hash;
        }

        console.log('InitLedger selesai - WFH Blockchain ready!');
    }

    // ============================================================
    // CreateActivity: Tambah aktivitas baru dengan blockchain fields
    // REST API sudah menghitung hash + nonce sebelum memanggil ini
    // ============================================================
    async CreateActivity(ctx, id, employeeName, department, activity, duration, activityType, notes, date, blockIndex, previousHash, hash, nonce, timestamp) {
        const exists = await this.ActivityExists(ctx, id);
        if (exists) {
            throw new Error(`Activity ${id} sudah ada dalam blockchain!`);
        }

        // Verifikasi hash yang dikirim REST API
        const expectedHash = computeHash(
            parseInt(blockIndex), previousHash, timestamp,
            id, employeeName, department, activity,
            Number(duration), activityType, notes, date,
            parseInt(nonce)
        );
        if (expectedHash !== hash) {
            throw new Error(`Hash tidak valid! Proof of Work gagal verifikasi.`);
        }

        const wfhActivity = {
            ID:           id,
            EmployeeName: employeeName,
            Department:   department,
            Activity:     activity,
            Duration:     Number(duration),
            ActivityType: activityType,
            Notes:        notes,
            Date:         date,
            Timestamp:    timestamp,
            BlockIndex:   parseInt(blockIndex),
            PreviousHash: previousHash,
            Hash:         hash,
            Nonce:        parseInt(nonce),
            DocType:      'wfh_activity',
        };

        await ctx.stub.putState(id, Buffer.from(stringify(sortKeysRecursive(wfhActivity))));
        return JSON.stringify(wfhActivity);
    }

    // ============================================================
    // ReadActivity: Baca satu aktivitas
    // ============================================================
    async ReadActivity(ctx, id) {
        const activityJSON = await ctx.stub.getState(id);
        if (!activityJSON || activityJSON.length === 0) {
            throw new Error(`Activity ${id} tidak ditemukan!`);
        }
        return activityJSON.toString();
    }

    // ============================================================
    // UpdateActivity: Update aktivitas (hash tidak di-update)
    // Data diubah tapi hash lama tetap → ValidateChain akan deteksi
    // ============================================================
    async UpdateActivity(ctx, id, employeeName, department, activity, duration, activityType, notes, date) {
        const exists = await this.ActivityExists(ctx, id);
        if (!exists) {
            throw new Error(`Activity ${id} tidak ditemukan!`);
        }

        const existing = JSON.parse((await ctx.stub.getState(id)).toString());

        const updatedActivity = {
            ID:           id,
            EmployeeName: employeeName,
            Department:   department,
            Activity:     activity,
            Duration:     Number(duration),
            ActivityType: activityType,
            Notes:        notes,
            Date:         date,
            Timestamp:    existing.Timestamp,
            BlockIndex:   existing.BlockIndex,
            PreviousHash: existing.PreviousHash,
            Hash:         existing.Hash,
            Nonce:        existing.Nonce,
            DocType:      'wfh_activity',
        };

        await ctx.stub.putState(id, Buffer.from(stringify(sortKeysRecursive(updatedActivity))));
        return JSON.stringify(updatedActivity);
    }

    // ============================================================
    // DeleteActivity: Hapus aktivitas
    // ============================================================
    async DeleteActivity(ctx, id) {
        const exists = await this.ActivityExists(ctx, id);
        if (!exists) {
            throw new Error(`Activity ${id} tidak ditemukan!`);
        }
        return ctx.stub.deleteState(id);
    }

    // ============================================================
    // ActivityExists: Cek apakah aktivitas ada
    // ============================================================
    async ActivityExists(ctx, id) {
        const activityJSON = await ctx.stub.getState(id);
        return activityJSON && activityJSON.length > 0;
    }

    // ============================================================
    // GetAllActivities: Ambil semua aktivitas
    // ============================================================
    async GetAllActivities(ctx) {
        const allResults = [];
        const iterator = await ctx.stub.getStateByRange('', '');
        let result = await iterator.next();

        while (!result.done) {
            const strValue = Buffer.from(result.value.value.toString()).toString('utf8');
            let record;
            try { record = JSON.parse(strValue); } catch (err) { record = strValue; }
            if (record.DocType === 'wfh_activity' || record.docType === 'wfh_activity') {
                allResults.push(record);
            }
            result = await iterator.next();
        }

        return JSON.stringify(allResults);
    }

    // ============================================================
    // GetLastBlock: Ambil block terakhir (BlockIndex tertinggi)
    // Dipakai REST API untuk chaining block baru
    // ============================================================
    async GetLastBlock(ctx) {
        const iterator = await ctx.stub.getStateByRange('', '');
        let result = await iterator.next();
        let lastBlock = null;

        while (!result.done) {
            const strValue = Buffer.from(result.value.value.toString()).toString('utf8');
            let record;
            try { record = JSON.parse(strValue); } catch (err) { record = null; }

            if (record && (record.DocType === 'wfh_activity' || record.docType === 'wfh_activity')) {
                if (!lastBlock || (record.BlockIndex || 0) > (lastBlock.BlockIndex || 0)) {
                    lastBlock = record;
                }
            }
            result = await iterator.next();
        }

        if (!lastBlock) {
            return JSON.stringify({ BlockIndex: 0, Hash: '0'.repeat(64) });
        }
        return JSON.stringify(lastBlock);
    }

    // ============================================================
    // ValidateChain: Validasi integritas seluruh blockchain
    // Re-compute hash tiap block dan cek previous hash chain
    // ============================================================
    async ValidateChain(ctx) {
        const iterator = await ctx.stub.getStateByRange('', '');
        let result = await iterator.next();
        const activities = [];

        while (!result.done) {
            const strValue = Buffer.from(result.value.value.toString()).toString('utf8');
            let record;
            try { record = JSON.parse(strValue); } catch (err) { record = null; }
            if (record && (record.DocType === 'wfh_activity' || record.docType === 'wfh_activity')) {
                activities.push(record);
            }
            result = await iterator.next();
        }

        // Urutkan berdasarkan BlockIndex
        activities.sort((a, b) => (a.BlockIndex || 0) - (b.BlockIndex || 0));

        const errors = [];
        let expectedPrevHash = '0'.repeat(64);

        for (const act of activities) {
            // Cek chain: previousHash harus sesuai dengan hash block sebelumnya
            if (act.PreviousHash !== expectedPrevHash) {
                errors.push({
                    blockIndex: act.BlockIndex,
                    id:         act.ID,
                    type:       'chain_broken',
                    message:    `PreviousHash tidak cocok pada Block #${act.BlockIndex}`,
                });
            }

            // Re-compute hash dan bandingkan
            const computedHash = computeHash(
                act.BlockIndex, act.PreviousHash, act.Timestamp,
                act.ID, act.EmployeeName, act.Department, act.Activity,
                act.Duration, act.ActivityType, act.Notes, act.Date,
                act.Nonce
            );

            if (computedHash !== act.Hash) {
                errors.push({
                    blockIndex:    act.BlockIndex,
                    id:            act.ID,
                    type:          'hash_invalid',
                    message:       `Hash tidak valid pada Block #${act.BlockIndex} (${act.ID}) — data kemungkinan dimanipulasi!`,
                    storedHash:    (act.Hash || '').substring(0, 16) + '...',
                    computedHash:  computedHash.substring(0, 16) + '...',
                });
            }

            expectedPrevHash = act.Hash;
        }

        return JSON.stringify({
            valid:       errors.length === 0,
            totalBlocks: activities.length,
            errors:      errors,
            message:     errors.length === 0
                ? `Semua ${activities.length} block valid. Blockchain terjaga integritasnya.`
                : `Ditemukan ${errors.length} masalah pada blockchain!`,
        });
    }

    // ============================================================
    // AuthUser: Autentikasi user untuk login
    // ============================================================
    async AuthUser(ctx, username, password) {
        const userBytes = await ctx.stub.getState('USER_' + username);
        if (!userBytes || userBytes.length === 0) {
            return JSON.stringify({ success: false, message: 'User tidak ditemukan' });
        }

        const user = JSON.parse(userBytes.toString());
        const inputHash = hashPassword(password);

        if (inputHash !== user.PasswordHash) {
            return JSON.stringify({ success: false, message: 'Password salah' });
        }

        return JSON.stringify({
            success: true,
            user: {
                username: user.Username,
                name:     user.Name,
                role:     user.Role,
            },
        });
    }

    // ============================================================
    // CreateUser: Buat user baru
    // ============================================================
    async CreateUser(ctx, username, password, role, name) {
        const existing = await ctx.stub.getState('USER_' + username);
        if (existing && existing.length > 0) {
            throw new Error(`User ${username} sudah ada!`);
        }

        const userDoc = {
            Username:     username,
            PasswordHash: hashPassword(password),
            Role:         role,
            Name:         name,
            DocType:      'user',
        };

        await ctx.stub.putState(
            'USER_' + username,
            Buffer.from(stringify(sortKeysRecursive(userDoc)))
        );

        return JSON.stringify({ success: true, username, role, name });
    }

    // ============================================================
    // GetActivitiesByEmployee: Filter by nama karyawan
    // ============================================================
    async GetActivitiesByEmployee(ctx, employeeName) {
        const allResults = [];
        const iterator = await ctx.stub.getStateByRange('', '');
        let result = await iterator.next();

        while (!result.done) {
            const strValue = Buffer.from(result.value.value.toString()).toString('utf8');
            let record;
            try { record = JSON.parse(strValue); } catch (err) { record = strValue; }
            if (
                (record.DocType === 'wfh_activity' || record.docType === 'wfh_activity') &&
                record.EmployeeName === employeeName
            ) {
                allResults.push(record);
            }
            result = await iterator.next();
        }

        return JSON.stringify(allResults);
    }

    // ============================================================
    // GetActivityHistory: Riwayat perubahan suatu aktivitas
    // ============================================================
    async GetActivityHistory(ctx, id) {
        const allResults = [];
        const iterator = await ctx.stub.getHistoryForKey(id);
        let result = await iterator.next();

        while (!result.done) {
            const historyRecord = {
                TxId:      result.value.txId,
                Timestamp: new Date(result.value.timestamp.seconds.low * 1000).toISOString(),
                IsDelete:  result.value.isDelete,
                Value:     result.value.isDelete ? null : JSON.parse(result.value.value.toString('utf8')),
            };
            allResults.push(historyRecord);
            result = await iterator.next();
        }

        return JSON.stringify(allResults);
    }

    // ============================================================
    // GetActivitiesByDate: Filter by tanggal
    // ============================================================
    async GetActivitiesByDate(ctx, date) {
        const allResults = [];
        const iterator = await ctx.stub.getStateByRange('', '');
        let result = await iterator.next();

        while (!result.done) {
            const strValue = Buffer.from(result.value.value.toString()).toString('utf8');
            let record;
            try { record = JSON.parse(strValue); } catch (err) { record = strValue; }
            if (
                (record.DocType === 'wfh_activity' || record.docType === 'wfh_activity') &&
                record.Date === date
            ) {
                allResults.push(record);
            }
            result = await iterator.next();
        }

        return JSON.stringify(allResults);
    }
}

module.exports = WFHActivityContract;
