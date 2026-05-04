<?php
/**
 * IMPORTANT: This file contains the corrected database query patterns.
 * All prepare() calls should store the result in a variable, then call bindValue() and execute() on that variable.
 * 
 * Pattern:
 * $stmt = $this->db->prepare($sql);
 * $stmt->bindValue(':param', $value);
 * $stmt->execute();
 * 
 * NOT:
 * $this->db->prepare($sql);
 * $this->db->bindValue(':param', $value);
 * $this->db->execute();
 */
