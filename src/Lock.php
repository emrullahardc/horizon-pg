<?php

namespace HorizonPg;

use Illuminate\Database\ConnectionInterface;

class Lock
{
    public $db;

    public function __construct(ConnectionInterface $db)
    {
        $this->db = $db;
    }

    public function with($key, $callback, $seconds = 60)
    {
        if ($this->get($key, $seconds)) {
            try {
                call_user_func($callback);
            } finally {
                $this->release($key);
            }
        }
    }

    public function exists($key)
    {
        $lockId = crc32($key) & 0x7FFFFFFF;

        $result = $this->db->selectOne(
            'SELECT pg_try_advisory_lock(?) AS acquired',
            [$lockId]
        );

        if ($result->acquired) {
            $this->db->statement('SELECT pg_advisory_unlock(?)', [$lockId]);

            return false;
        }

        return true;
    }

    public function get($key, $seconds = 60)
    {
        $lockId = crc32($key) & 0x7FFFFFFF;

        $result = $this->db->selectOne(
            'SELECT pg_try_advisory_lock(?) AS acquired',
            [$lockId]
        );

        return (bool) $result->acquired;
    }

    public function release($key)
    {
        $lockId = crc32($key) & 0x7FFFFFFF;

        $this->db->statement('SELECT pg_advisory_unlock(?)', [$lockId]);
    }

    public function connection()
    {
        return $this->db;
    }
}
