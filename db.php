<?php
// Ensure session is started to support the mock session-based database
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Define the temporary SQLite file specific to this session
$sqlite_file = sys_get_temp_dir() . '/lesaja_' . session_id() . '.sqlite';

// If database backup exists in session, restore it
if (isset($_SESSION['sqlite_db_data'])) {
    file_put_contents($sqlite_file, base64_decode($_SESSION['sqlite_db_data']));
}

$is_new = !file_exists($sqlite_file) || filesize($sqlite_file) === 0;

// Initialize tables and mock data if it is a new session
if ($is_new) {
    $db = new SQLite3($sqlite_file);
    
    // Create standard relational tables using SQLite syntax
    $db->exec("
        CREATE TABLE IF NOT EXISTS pengguna (
            id_pengguna INTEGER PRIMARY KEY AUTOINCREMENT,
            nama_pengguna TEXT UNIQUE,
            kata_sandi TEXT,
            role TEXT,
            foto_profil TEXT
        );
    ");
    $db->exec("
        CREATE TABLE IF NOT EXISTS pengajar (
            id_pengajar INTEGER PRIMARY KEY AUTOINCREMENT,
            nama_pengajar TEXT UNIQUE,
            kata_sandi TEXT,
            role TEXT DEFAULT 'pengajar',
            foto_profil TEXT,
            jenjang_keahlian TEXT,
            keahlian TEXT,
            harga_layanan REAL,
            no TEXT,
            riwayat_pendidikan TEXT,
            lokasi TEXT,
            rating REAL DEFAULT 0.0
        );
    ");
    $db->exec("
        CREATE TABLE IF NOT EXISTS permintaan_les (
            id_permintaan INTEGER PRIMARY KEY AUTOINCREMENT,
            id_pengguna INTEGER,
            id_pengajar INTEGER,
            nama_pemesan_les TEXT,
            keahlian_les TEXT,
            jenjang_les TEXT,
            lokasi_les_diajukan TEXT,
            tanggal_les_diajukan TEXT,
            jam_les_diajukan TEXT,
            harga_saat_booking REAL,
            status_permintaan TEXT DEFAULT 'menunggu'
        );
    ");
    $db->exec("
        CREATE TABLE IF NOT EXISTS tugas_soal (
            id_soal INTEGER PRIMARY KEY AUTOINCREMENT,
            id_pengguna INTEGER,
            id_pengajar INTEGER,
            soal TEXT,
            jawaban TEXT,
            foto_tugas TEXT,
            tanggal_kirim TEXT,
            rating_diberikan INTEGER,
            review_text TEXT
        );
    ");
    $db->exec("
        CREATE TABLE IF NOT EXISTS rating_review (
            id_rating INTEGER PRIMARY KEY AUTOINCREMENT,
            id_les INTEGER,
            id_pengguna INTEGER,
            id_pengajar INTEGER,
            rating REAL,
            review TEXT,
            updated_at TEXT
        );
    ");

    // Pre-populate mock users with the password 'password'
    $pass_default = password_hash('password', PASSWORD_DEFAULT);
    
    // Standard student (pengguna)
    $db->exec("INSERT OR IGNORE INTO pengguna (nama_pengguna, kata_sandi, role, foto_profil) VALUES ('pengguna', '$pass_default', 'pengguna', '')");

    // Standard tutors (pengajar)
    $db->exec("INSERT OR IGNORE INTO pengajar (nama_pengajar, kata_sandi, role, foto_profil, jenjang_keahlian, keahlian, harga_layanan, no, riwayat_pendidikan, lokasi, rating) VALUES 
        ('Budi Santoso', '$pass_default', 'pengajar', '', 'SMA', 'Matematika', 150000, '08123456789', 'S1 Pendidikan Matematika UI', 'Jakarta', 4.8),
        ('Siti Aminah', '$pass_default', 'pengajar', '', 'SMP', 'Bahasa Inggris', 120000, '08129876543', 'S1 Sastra Inggris UNESA', 'Surabaya', 4.9),
        ('Ahmad Fauzi', '$pass_default', 'pengajar', '', 'SMA', 'Fisika', 160000, '08561122334', 'S2 Fisika ITB', 'Bandung', 4.7)
    ");
    
    $db->close();
    
    // Save initial backup to session immediately
    $_SESSION['sqlite_db_data'] = base64_encode(file_get_contents($sqlite_file));
}

/**
 * Mock classes to perfectly mimic mysqli, mysqli_result, and mysqli_stmt
 * and route all SQL queries to the session-based SQLite database.
 */

class MockMySQLiResult {
    private $rows = [];
    private $index = 0;
    public $num_rows = 0;

    public function __construct($sqlite_result) {
        if ($sqlite_result) {
            while ($row = $sqlite_result->fetchArray(SQLITE3_ASSOC)) {
                $this->rows[] = $row;
            }
        }
        $this->num_rows = count($this->rows);
    }

    public function fetch_assoc() {
        if ($this->index < $this->num_rows) {
            return $this->rows[$this->index++];
        }
        return null;
    }

    public function fetch_row() {
        if ($this->index < $this->num_rows) {
            return array_values($this->rows[$this->index++]);
        }
        return null;
    }

    public function close() { return true; }
    public function free() { return true; }
    public function free_result() { return true; }
}

class MockMySQLiStmt {
    private $conn;
    private $sqlite;
    private $sql;
    private $params = [];
    public $error = '';
    private $result;

    public function __construct($conn, $sqlite, $sql) {
        $this->conn = $conn;
        $this->sqlite = $sqlite;
        $this->sql = $sql;
    }

    public function bind_param($types, &...$vars) {
        $this->params = $vars;
        return true;
    }

    public function execute() {
        $stmt = $this->sqlite->prepare($this->sql);
        if (!$stmt) {
            $this->error = $this->sqlite->lastErrorMsg();
            $this->conn->error = $this->error;
            return false;
        }
        foreach ($this->params as $index => $val) {
            $stmt->bindValue($index + 1, $val);
        }
        $res = $stmt->execute();
        if (!$res) {
            $this->error = $this->sqlite->lastErrorMsg();
            $this->conn->error = $this->error;
            return false;
        }
        $this->result = $res;
        
        $this->conn->insert_id = $this->sqlite->lastInsertRowID();
        $this->conn->affected_rows = $this->sqlite->changes();
        return true;
    }

    public function get_result() {
        return new MockMySQLiResult($this->result);
    }

    public function close() { return true; }
}

class MockMySQLi extends mysqli {
    public $sqlite;
    private $mock_error = '';
    private $mock_insert_id = 0;
    private $mock_affected_rows = 0;
    private $mock_connect_error = null;
    private $mock_connect_errno = 0;

    public function __construct($sqlite_file) {
        try {
            $this->sqlite = new SQLite3($sqlite_file);
        } catch (Exception $e) {
            $this->mock_connect_error = $e->getMessage();
            $this->mock_connect_errno = 2002;
        }
    }

    public function __get($name) {
        if ($name === 'error') return $this->mock_error;
        if ($name === 'insert_id') return $this->mock_insert_id;
        if ($name === 'affected_rows') return $this->mock_affected_rows;
        if ($name === 'connect_error') return $this->mock_connect_error;
        if ($name === 'connect_errno') return $this->mock_connect_errno;
        return parent::__get($name);
    }

    public function __set($name, $value) {
        if ($name === 'error') $this->mock_error = $value;
        elseif ($name === 'insert_id') $this->mock_insert_id = $value;
        elseif ($name === 'affected_rows') $this->mock_affected_rows = $value;
        elseif ($name === 'connect_error') $this->mock_connect_error = $value;
        elseif ($name === 'connect_errno') $this->mock_connect_errno = $value;
        else parent::__set($name, $value);
    }

    #[\ReturnTypeWillChange]
    public function query($query, $result_mode = MYSQLI_STORE_RESULT) {
        // Adapt MySQL-specific datetime functions to SQLite
        $query = str_ireplace('NOW()', "datetime('now', 'localtime')", $query);
        $query = str_ireplace('CURRENT_TIMESTAMP', "datetime('now', 'localtime')", $query);

        $is_select = preg_match('/^\s*(SELECT|SHOW|DESCRIBE|EXPLAIN)/i', $query);

        if ($is_select) {
            $res = @$this->sqlite->query($query);
            if (!$res) {
                $this->mock_error = $this->sqlite->lastErrorMsg();
                return false;
            }
            return new MockMySQLiResult($res);
        } else {
            $res = @$this->sqlite->exec($query);
            if (!$res) {
                $this->mock_error = $this->sqlite->lastErrorMsg();
                return false;
            }
            $this->mock_insert_id = $this->sqlite->lastInsertRowID();
            $this->mock_affected_rows = $this->sqlite->changes();
            return true;
        }
    }

    #[\ReturnTypeWillChange]
    public function prepare($query) {
        $query = str_ireplace('NOW()', "datetime('now', 'localtime')", $query);
        $query = str_ireplace('CURRENT_TIMESTAMP', "datetime('now', 'localtime')", $query);
        return new MockMySQLiStmt($this, $this->sqlite, $query);
    }

    #[\ReturnTypeWillChange]
    public function real_escape_string($string) {
        return SQLite3::escapeString($string);
    }

    #[\ReturnTypeWillChange]
    public function escape_string($string) {
        return SQLite3::escapeString($string);
    }

    #[\ReturnTypeWillChange]
    public function set_charset($charset) { return true; }
    
    #[\ReturnTypeWillChange]
    public function close() {
        if ($this->sqlite) {
            $this->sqlite->close();
        }
        return true;
    }

    #[\ReturnTypeWillChange]
    public function ping() {
        return true;
    }
}

// Instantiate the custom database connection
$conn = new MockMySQLi($sqlite_file);

// Register shutdown handler to securely save database state to session and delete the temp file
register_shutdown_function(function() use ($sqlite_file) {
    if (file_exists($sqlite_file) && filesize($sqlite_file) > 0) {
        $_SESSION['sqlite_db_data'] = base64_encode(file_get_contents($sqlite_file));
        @unlink($sqlite_file);
    }
});
?>