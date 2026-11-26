<?php
declare(strict_types=1);
function hostedDb(): PDO {
    $host = getenv('SALON_DB_HOST');
    $name = getenv('SALON_DB_NAME');
    $port = getenv('SALON_DB_PORT') ?: '3306';
    if (!$host || !$name || !ctype_digit($port) || preg_match('/[;\s]/',$host.$name)) {
        throw new RuntimeException('Configure the hosted database.');
    }
    $ca = getenv('SALON_DB_CA_FILE') ?: '/etc/ssl/certs/ca-certificates.crt';
    return new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4",
        getenv('SALON_DB_USER'),getenv('SALON_DB_PASSWORD'),[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES=>false, PDO::MYSQL_ATTR_SSL_CA=>$ca,
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT=>true]);
}

/* Serialize requests from one browser session across function instances. */
final class DatabaseSessions implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface {
    private ?string $lock = null;
    public function open(string $path,string $name): bool { return true; }
    public function close(): bool {
        if ($this->lock !== null) query('SELECT RELEASE_LOCK(?)',[$this->lock]);
        $this->lock=null;
        return true;
    }
    public function read(string $id): string|false {
        $this->close();
        $this->lock=hash('sha256','salon-session:'.$id);
        if ((int)query('SELECT GET_LOCK(?,10)',[$this->lock])->fetchColumn()!==1) throw new RuntimeException('Session busy');
        return query('SELECT payload FROM web_sessions WHERE id=? AND expires_at>NOW()', [hash('sha256',$id)])->fetchColumn() ?: '';
    }
    public function write(string $id,string $data): bool {
        query('INSERT INTO web_sessions(id,payload,expires_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 30 MINUTE)) ON DUPLICATE KEY UPDATE payload=VALUES(payload),expires_at=VALUES(expires_at)',[hash('sha256',$id),$data]);
        return true;
    }
    public function validateId(string $id): bool {
        return (bool)query('SELECT 1 FROM web_sessions WHERE id=? AND expires_at>NOW()',[hash('sha256',$id)])->fetchColumn();
    }
    public function updateTimestamp(string $id,string $data): bool {return $this->write($id,$data);}
    public function destroy(string $id): bool {
        query('DELETE FROM web_sessions WHERE id=?',[hash('sha256',$id)]);
        return true;
    }
    public function gc(int $max_lifetime): int|false {
        return query('DELETE FROM web_sessions WHERE expires_at<=NOW()')->rowCount();
    }
}
