<?php
/**
 * Compatibility shim for mysqli_stmt::get_result() when the PHP mysqlnd
 * extension is not available on the server.
 *
 * Usage:  replace  $stmt->get_result()  with  stmt_get_result($stmt)
 *
 * Returns a MysqliResultCompat object that exposes the same surface as the
 * native mysqli_result object used in this codebase:
 *   - $result->num_rows
 *   - $result->fetch_assoc()
 *   - $result->fetch_all()
 *   - $result->free()
 */

function stmt_get_result($stmt) {
    $meta = $stmt->result_metadata();
    if (!$meta) {
        return new MysqliResultCompat([]);
    }

    $fields = [];
    while ($field = $meta->fetch_field()) {
        $fields[] = $field->name;
    }
    $meta->free();

    // Build a reference array pointing into $row so bind_result() populates it.
    $row  = array_fill_keys($fields, null);
    $refs = [];
    foreach ($fields as $f) {
        $refs[] = &$row[$f];
    }
    call_user_func_array([$stmt, 'bind_result'], $refs);

    $rows = [];
    while ($stmt->fetch()) {
        $copy = [];
        foreach ($fields as $f) {
            $copy[$f] = $row[$f];
        }
        $rows[] = $copy;
    }

    return new MysqliResultCompat($rows);
}

class MysqliResultCompat {
    /** @var int */
    public $num_rows;

    /** @var array */
    private $rows;

    /** @var int */
    private $pos = 0;

    public function __construct(array $rows) {
        $this->rows    = $rows;
        $this->num_rows = count($rows);
    }

    public function fetch_assoc() {
        if ($this->pos >= $this->num_rows) {
            return null;
        }
        return $this->rows[$this->pos++];
    }

    public function fetch_all($mode = MYSQLI_ASSOC) {
        return $this->rows;
    }

    public function free() {
        // no-op; kept for API compatibility
    }
}
