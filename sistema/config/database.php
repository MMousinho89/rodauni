<?php
// ==========================================================
// Arquivo: config/database.php
// Função: Criar a conexão PDO ($pdo) com o MySQL
// Esse arquivo será incluído (require_once) em outras páginas
// como login_action.php, consultas futuras, etc.
// ==========================================================

// ----------------------------------------------------------
// 1. Configurações de acesso ao banco de dados
// ----------------------------------------------------------
// IMPORTANTE: esses valores são do ambiente local (XAMPP)
// No futuro, em produção, isso muda.

$DB_HOST = 'localhost';      // Servidor do banco. No XAMPP é sempre localhost.
$DB_NAME = 'rodauni';        // <<< CORRIGIDO: seu banco no phpMyAdmin é "rodauni"
$DB_USER = 'root';           // Usuário padrão do MySQL no XAMPP.
$DB_PASS = '';               // Senha do MySQL no XAMPP (vazio por padrão).

// ----------------------------------------------------------
// 2. Tentar abrir a conexão PDO
// ----------------------------------------------------------
// PDO é a interface do PHP para acessar bancos (MySQL, etc).
// Aqui a gente cria a variável $pdo e deixa ela disponível
// para quem incluir esse arquivo.
try {

    // Monta a string de conexão (DSN = Data Source Name)
    $dsn = "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4";

    // Cria a conexão e guarda em $pdo
    $pdo = new PDO(
        $dsn,          // dados do servidor e banco
        $DB_USER,      // usuário
        $DB_PASS,      // senha
        [
            // Modo de erro: lançar exceção (bom pra ver problemas rápido)
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,

            // Formato padrão dos resultados: array associativo
            // Exemplo: $row['nome'], $row['email']
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

} catch (PDOException $e) {

    // Se der erro pra conectar (por exemplo MySQL desligado),
    // o sistema morre aqui e mostra a mensagem.
    // Esse "die" é ok em ambiente de desenvolvimento.
    die("❌ Erro ao conectar ao banco de dados: " . $e->getMessage());
}
