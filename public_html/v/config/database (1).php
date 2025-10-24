<?php
// config/database.php

// Mock database connection for testing environment
// In production, this would connect to a real MySQL database

// Create a mock PDO object for testing
class MockPDO {
    public function prepare($statement) {
        return new MockPDOStatement();
    }
    
    public function query($statement) {
        return new MockPDOStatement();
    }
    
    public function exec($statement) {
        return true;
    }
    
    public function lastInsertId() {
        return rand(1, 1000);
    }
    
    public function beginTransaction() {
        return true;
    }
    
    public function commit() {
        return true;
    }
    
    public function rollback() {
        return true;
    }
}

class MockPDOStatement {
    public function execute($params = []) {
        return true;
    }
    
    public function fetch($mode = null) {
        return false; // No data for testing
    }
    
    public function fetchAll($mode = null) {
        return []; // Empty array for testing
    }
}

// Use mock PDO for testing
$pdo = new MockPDO();

// Funções auxiliares de autenticação (garantir que estão definidas globalmente)
if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
}

if (!function_exists('isAdmin')) {
    function isAdmin() {
        return isLoggedIn() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
    }
}

if (!function_exists('isSubscriber')) {
    function isSubscriber() {
        // CORREÇÃO: Admin tem acesso completo, incluindo videoteca
        if (isAdmin()) {
            return true;
        }
        return isLoggedIn() && isset($_SESSION['is_subscriber']) && $_SESSION['is_subscriber'] == 1;
    }
}

// Função adicional para verificar acesso à videoteca especificamente
if (!function_exists('hasVideotecaAccess')) {
    function hasVideotecaAccess() {
        // Admin ou assinante têm acesso à videoteca
        return isAdmin() || isSubscriber();
    }
}

?>
