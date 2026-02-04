<?php

namespace Nemesis\Queue\Drivers;

use Nemesis\Queue\QueueDriver;
use Nemesis\Queue\Job;
use Nemesis\Core\Database;
use PDO;

class DatabaseDriver implements QueueDriver {
    protected $table = 'jobs';

    public function push(Job $job) {
        $sql = "INSERT INTO {$this->table} (payload, available_at, created_at) VALUES (:payload, :available_at, :created_at)";
        $stmt = Database::connect()->prepare($sql);
        return $stmt->execute([
            'payload' => serialize($job),
            'available_at' => time() + $job->getDelay(),
            'created_at' => time()
        ]);
    }

    public function pop() {
        $pdo = Database::connect();
        $pdo->beginTransaction();

        try {
            $sql = "SELECT * FROM {$this->table} WHERE reserved_at IS NULL AND available_at <= :now ORDER BY id ASC LIMIT 1 FOR UPDATE";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['now' => time()]);
            $job = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($job) {
                $pdo->prepare("UPDATE {$this->table} SET reserved_at = :now, attempts = attempts + 1 WHERE id = :id")
                    ->execute(['now' => time(), 'id' => $job['id']]);
                $pdo->commit();
                return $job;
            }

            $pdo->rollBack();
            return null;
        } catch (\Exception $e) {
            $pdo->rollBack();
            return null;
        }
    }

    public function delete($id) {
        $stmt = Database::connect()->prepare("DELETE FROM {$this->table} WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    public function release($id, $delay = 0) {
        $stmt = Database::connect()->prepare("UPDATE {$this->table} SET reserved_at = NULL, available_at = :available_at WHERE id = :id");
        return $stmt->execute([
            'available_at' => time() + $delay,
            'id' => $id
        ]);
    }
}
