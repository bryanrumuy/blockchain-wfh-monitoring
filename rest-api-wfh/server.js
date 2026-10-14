'use strict';

const express = require('express');
const cors    = require('cors');
const grpc    = require('@grpc/grpc-js');
const { connect, hash, signers } = require('@hyperledger/fabric-gateway');
const crypto  = require('crypto');
const fs      = require('fs');
const path    = require('path');

const app  = express();
const PORT = 3000;

app.use(cors());
app.use(express.json());

// ============================================================
// KONFIGURASI HYPERLEDGER FABRIC
// ============================================================
const channelName   = 'mychannel';
const chaincodeName = 'wfh';
const mspId         = 'Org1MSP';

const cryptoPath = path.resolve(
    __dirname, '..', '..', '..', 'test-network',
    'organizations', 'peerOrganizations', 'org1.example.com'
);
const keyDirectoryPath  = path.resolve(cryptoPath, 'users', 'User1@org1.example.com', 'msp', 'keystore');
const certDirectoryPath = path.resolve(cryptoPath, 'users', 'User1@org1.example.com', 'msp', 'signcerts');
const tlsCertPath       = path.resolve(cryptoPath, 'peers', 'peer0.org1.example.com', 'tls', 'ca.crt');
const peerEndpoint      = 'localhost:7051';
const peerHostAlias     = 'peer0.org1.example.com';
const utf8Decoder       = new TextDecoder();

// ============================================================
// HELPER: Koneksi ke chaincode
// ============================================================
async function getContract() {
    const tlsRootCert    = fs.readFileSync(tlsCertPath);
    const tlsCredentials = grpc.credentials.createSsl(tlsRootCert);
    const client         = new grpc.Client(peerEndpoint, tlsCredentials, {
        'grpc.ssl_target_name_override': peerHostAlias,
    });

    const files      = fs.readdirSync(keyDirectoryPath);
    const privateKey = crypto.createPrivateKey(fs.readFileSync(path.resolve(keyDirectoryPath, files[0])));

    const certFiles    = fs.readdirSync(certDirectoryPath);
    const credentials  = fs.readFileSync(path.resolve(certDirectoryPath, certFiles[0])).toString();

    const gateway = connect({
        client,
        identity: { mspId, credentials: Buffer.from(credentials) },
        signer:   signers.newPrivateKeySigner(privateKey),
        hash:     hash.sha256,
    });

    const network  = gateway.getNetwork(channelName);
    const contract = network.getContract(chaincodeName);
    return { contract, gateway, client };
}

// ============================================================
// HELPER: Hitung SHA-256 hash block
// Rumus: SHA256(blockIndex|prevHash|timestamp|id|...|nonce)
// ============================================================
function computeBlockHash(blockIndex, previousHash, timestamp, id, employeeName, department, activity, duration, activityType, notes, date, nonce) {
    const content = [
        String(blockIndex), previousHash, timestamp,
        id, employeeName, department, activity,
        String(duration), activityType, notes, date,
        String(nonce)
    ].join('|');
    return crypto.createHash('sha256').update(content).digest('hex');
}

// ============================================================
// HELPER: Proof of Work — cari nonce sampai hash diawali "000"
// ============================================================
function mineBlock(blockIndex, previousHash, timestamp, id, employeeName, department, activity, duration, activityType, notes, date) {
    let nonce = 0;
    let hash;
    do {
        nonce++;
        hash = computeBlockHash(blockIndex, previousHash, timestamp, id, employeeName, department, activity, duration, activityType, notes, date, nonce);
    } while (!hash.startsWith('000'));
    return { hash, nonce };
}

// ============================================================
// ENDPOINT: POST /api/login
// Autentikasi user via blockchain
// ============================================================
app.post('/api/login', async (req, res) => {
    try {
        const { username, password } = req.body;
        if (!username || !password) {
            return res.status(400).json({ success: false, message: 'Username dan password wajib diisi' });
        }

        const { contract, gateway, client } = await getContract();
        const result = await contract.evaluateTransaction('AuthUser', username, password);
        const json   = JSON.parse(utf8Decoder.decode(result));

        res.json(json);
        gateway.close();
        client.close();
    } catch (err) {
        console.error('Login error:', err);
        res.status(500).json({ success: false, message: 'Gagal menghubungi blockchain: ' + err.message });
    }
});

// ============================================================
// ENDPOINT: GET /api/activities
// ============================================================
app.get('/api/activities', async (req, res) => {
    try {
        const { contract, gateway, client } = await getContract();
        const result = await contract.evaluateTransaction('GetAllActivities');
        const json   = JSON.parse(utf8Decoder.decode(result));

        res.json({ success: true, total: json.length, data: json });
        gateway.close();
        client.close();
    } catch (err) {
        console.error('Error GetAllActivities:', err);
        res.status(500).json({ success: false, error: err.message });
    }
});

// ============================================================
// ENDPOINT: GET /api/activities/:id
// ============================================================
app.get('/api/activities/:id', async (req, res) => {
    try {
        const { contract, gateway, client } = await getContract();
        const result = await contract.evaluateTransaction('ReadActivity', req.params.id);
        const json   = JSON.parse(utf8Decoder.decode(result));

        res.json({ success: true, data: json });
        gateway.close();
        client.close();
    } catch (err) {
        console.error('Error ReadActivity:', err);
        res.status(404).json({ success: false, error: err.message });
    }
});

// ============================================================
// ENDPOINT: POST /api/activities
// Catat aktivitas baru + Proof of Work
// ============================================================
app.post('/api/activities', async (req, res) => {
    try {
        const { contract, gateway, client } = await getContract();
        const {
            id, employeeName, department, activity,
            duration, activityType, notes, date
        } = req.body;

        if (!id || !employeeName || !activity || !duration) {
            return res.status(400).json({
                success: false,
                error: 'Field id, employeeName, activity, dan duration wajib diisi!'
            });
        }

        // Ambil block terakhir untuk chaining
        const lastBlockResult = await contract.evaluateTransaction('GetLastBlock');
        const lastBlock       = JSON.parse(utf8Decoder.decode(lastBlockResult));

        const blockIndex   = (lastBlock.BlockIndex || 0) + 1;
        const previousHash = lastBlock.Hash || '0'.repeat(64);
        const timestamp    = new Date().toISOString();
        const dur          = Number(duration);
        const dept         = department   || '';
        const type         = activityType || 'Productive';
        const note         = notes        || '';
        const dateStr      = date         || new Date().toISOString().split('T')[0];

        // Proof of Work — cari nonce sampai hash diawali "000"
        console.log(`Mining block ${blockIndex}...`);
        const startTime    = Date.now();
        const { hash: blockHash, nonce } = mineBlock(
            blockIndex, previousHash, timestamp,
            id, employeeName, dept, activity, dur, type, note, dateStr
        );
        const miningTime = Date.now() - startTime;
        console.log(`Block ${blockIndex} mined | Hash: ${blockHash.substring(0,12)}... | Nonce: ${nonce} | Time: ${miningTime}ms`);

        // Submit ke chaincode
        const result = await contract.submitTransaction(
            'CreateActivity',
            id, employeeName, dept, activity,
            String(dur), type, note, dateStr,
            String(blockIndex), previousHash, blockHash,
            String(nonce), timestamp
        );

        const json = JSON.parse(utf8Decoder.decode(result));

        res.status(201).json({
            success: true,
            message: `Aktivitas ${id} berhasil dicatat ke blockchain!`,
            blockchain: {
                blockIndex,
                hash:        blockHash,
                previousHash,
                nonce,
                powValid:    blockHash.startsWith('000'),
                miningTimeMs: miningTime,
            },
            data: json,
        });

        gateway.close();
        client.close();
    } catch (err) {
        console.error('Error CreateActivity:', err);
        res.status(500).json({ success: false, error: err.message });
    }
});

// ============================================================
// ENDPOINT: PUT /api/activities/:id
// ============================================================
app.put('/api/activities/:id', async (req, res) => {
    try {
        const { contract, gateway, client } = await getContract();
        const { id } = req.params;
        const { employeeName, department, activity, duration, activityType, notes, date } = req.body;

        const result = await contract.submitTransaction(
            'UpdateActivity',
            id, employeeName, department || '',
            activity, String(duration),
            activityType || 'Productive', notes || '',
            date || new Date().toISOString().split('T')[0]
        );

        const json = JSON.parse(utf8Decoder.decode(result));
        res.json({ success: true, message: `Aktivitas ${id} berhasil diperbarui!`, data: json });

        gateway.close();
        client.close();
    } catch (err) {
        console.error('Error UpdateActivity:', err);
        res.status(500).json({ success: false, error: err.message });
    }
});

// ============================================================
// ENDPOINT: DELETE /api/activities/:id
// ============================================================
app.delete('/api/activities/:id', async (req, res) => {
    try {
        const { contract, gateway, client } = await getContract();
        await contract.submitTransaction('DeleteActivity', req.params.id);

        res.json({ success: true, message: `Aktivitas ${req.params.id} berhasil dihapus!` });
        gateway.close();
        client.close();
    } catch (err) {
        console.error('Error DeleteActivity:', err);
        res.status(500).json({ success: false, error: err.message });
    }
});

// ============================================================
// ENDPOINT: GET /api/chain
// Ambil semua aktivitas diurutkan by BlockIndex (untuk visualisasi)
// ============================================================
app.get('/api/chain', async (req, res) => {
    try {
        const { contract, gateway, client } = await getContract();
        const result     = await contract.evaluateTransaction('GetAllActivities');
        const activities = JSON.parse(utf8Decoder.decode(result));

        activities.sort((a, b) => (a.BlockIndex || 0) - (b.BlockIndex || 0));

        res.json({ success: true, total: activities.length, chain: activities });
        gateway.close();
        client.close();
    } catch (err) {
        console.error('Error GetChain:', err);
        res.status(500).json({ success: false, error: err.message });
    }
});

// ============================================================
// ENDPOINT: GET /api/validate
// Validasi integritas seluruh blockchain
// ============================================================
app.get('/api/validate', async (req, res) => {
    try {
        const { contract, gateway, client } = await getContract();
        const result = await contract.evaluateTransaction('ValidateChain');
        const json   = JSON.parse(utf8Decoder.decode(result));

        res.json({ success: true, ...json });
        gateway.close();
        client.close();
    } catch (err) {
        console.error('Error ValidateChain:', err);
        res.status(500).json({ success: false, error: err.message });
    }
});

// ============================================================
// ENDPOINT: GET /api/employee/:name
// ============================================================
app.get('/api/employee/:name', async (req, res) => {
    try {
        const { contract, gateway, client } = await getContract();
        const employeeName = decodeURIComponent(req.params.name);
        const result       = await contract.evaluateTransaction('GetActivitiesByEmployee', employeeName);
        const json         = JSON.parse(utf8Decoder.decode(result));

        res.json({
            success:      true,
            employee:     employeeName,
            total:        json.length,
            totalMinutes: json.reduce((sum, a) => sum + (a.Duration || 0), 0),
            data:         json,
        });
        gateway.close();
        client.close();
    } catch (err) {
        console.error('Error GetByEmployee:', err);
        res.status(500).json({ success: false, error: err.message });
    }
});

// ============================================================
// ENDPOINT: GET /api/history/:id
// ============================================================
app.get('/api/history/:id', async (req, res) => {
    try {
        const { contract, gateway, client } = await getContract();
        const result = await contract.evaluateTransaction('GetActivityHistory', req.params.id);
        const json   = JSON.parse(utf8Decoder.decode(result));

        res.json({ success: true, id: req.params.id, history: json });
        gateway.close();
        client.close();
    } catch (err) {
        console.error('Error GetHistory:', err);
        res.status(500).json({ success: false, error: err.message });
    }
});

// ============================================================
// ENDPOINT: POST /api/init
// ============================================================
app.post('/api/init', async (req, res) => {
    try {
        const { contract, gateway, client } = await getContract();
        await contract.submitTransaction('InitLedger');

        res.json({ success: true, message: 'Data awal + user berhasil diinisialisasi ke blockchain!' });
        gateway.close();
        client.close();
    } catch (err) {
        console.error('Error InitLedger:', err);
        res.status(500).json({ success: false, error: err.message });
    }
});

// ============================================================
// ENDPOINT: GET /api/date/:date
// ============================================================
app.get('/api/date/:date', async (req, res) => {
    try {
        const { contract, gateway, client } = await getContract();
        const result = await contract.evaluateTransaction('GetActivitiesByDate', req.params.date);
        const json   = JSON.parse(utf8Decoder.decode(result));

        res.json({ success: true, date: req.params.date, total: json.length, data: json });
        gateway.close();
        client.close();
    } catch (err) {
        console.error('Error GetByDate:', err);
        res.status(500).json({ success: false, error: err.message });
    }
});

// ============================================================
// ENDPOINT: GET / — Health check
// ============================================================
app.get('/', (req, res) => {
    res.json({
        app:        'WFH Activity Monitoring REST API',
        version:    '2.0.0',
        blockchain: 'Hyperledger Fabric',
        features:   ['SHA-256 Hash', 'Proof of Work (difficulty: 000)', 'Chain Validation', 'User Auth'],
        endpoints: [
            'POST   /api/login              - Login via blockchain',
            'GET    /api/activities         - Semua aktivitas',
            'GET    /api/activities/:id     - Detail aktivitas',
            'POST   /api/activities         - Catat aktivitas (+ PoW)',
            'PUT    /api/activities/:id     - Update aktivitas',
            'DELETE /api/activities/:id     - Hapus aktivitas',
            'GET    /api/chain              - Chain terurut by BlockIndex',
            'GET    /api/validate           - Validasi integritas blockchain',
            'GET    /api/employee/:name     - Aktivitas per karyawan',
            'GET    /api/history/:id        - Riwayat perubahan',
            'GET    /api/date/:date         - Filter by tanggal',
            'POST   /api/init              - Init ledger',
        ],
    });
});

app.listen(PORT, () => {
    console.log('================================================');
    console.log('  WFH Blockchain Monitor - REST API v2.0');
    console.log('  Hyperledger Fabric + SHA-256 + Proof of Work');
    console.log('================================================');
    console.log(`  API: http://localhost:${PORT}`);
    console.log(`  PoW Difficulty: hash diawali "000"`);
    console.log('================================================');
});
