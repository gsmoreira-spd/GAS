<?php
/**
 * INSTALADOR AUTOMÁTICO DO SISTEMA
 *
 * Acesse pelo navegador: http://localhost/gasagua_erp/instalar.php
 * Em produção este arquivo é bloqueado automaticamente.
 */

// Bloquear acesso em ambiente de produção
$env = getenv('APP_ENV') ?: 'development';
if ($env === 'production') {
    http_response_code(403);
    die('Acesso negado. Remova ou proteja instalar.php em produção.');
}

// Configurações de conexão (ajuste se necessário)
$host = getenv('DB_HOST')     ?: 'localhost';
$user = getenv('DB_USERNAME') ?: 'root';
$pass = getenv('DB_PASSWORD') ?: '';
$db_name = getenv('DB_DATABASE') ?: 'gasagua_erp';

// Hash da senha admin123 gerado dinamicamente
$senha_admin = password_hash('admin123', PASSWORD_DEFAULT);

// Conectar sem selecionar banco
$conn = new mysqli($host, $user, $pass);
if ($conn->connect_error) {
    die("❌ Erro ao conectar no MySQL: " . $conn->connect_error);
}

echo "<style>body{font-family:Arial;background:#f4f7fb;padding:30px;}h1{color:#2563eb;}p{padding:10px;background:#fff;border-left:4px solid #10b981;margin:5px 0;}a{display:inline-block;margin-top:20px;padding:12px 25px;background:#2563eb;color:#fff;text-decoration:none;border-radius:6px;}</style>";
echo "<h1>🔧 Instalador - Sistema ERP Gás & Água</h1>";

// Lê o arquivo SQL
$sql_file = __DIR__ . '/database/gasagua_erp.sql';
if (!file_exists($sql_file)) {
    die("❌ Arquivo SQL não encontrado: $sql_file");
}

$sql = file_get_contents($sql_file);

// Substitui o placeholder do hash da senha
$sql = str_replace('$2y$10$YourHashWillBeReplacedByInstaller', $senha_admin, $sql);

// Executa cada query
if ($conn->multi_query($sql)) {
    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->more_results() && $conn->next_result());
    
    echo "<p>✅ Banco de dados criado com sucesso!</p>";
    echo "<p>✅ Tabelas criadas: empresas, filiais, usuarios, produtos, estoque, clientes, motoboys, pedidos, financeiro, logs</p>";
    echo "<p>✅ Dados iniciais inseridos.</p>";
    echo "<p>✅ Usuário admin criado.</p>";
    echo "<hr><h3>🔑 Credenciais de Acesso:</h3>";
    echo "<p><b>Email:</b> admin@sistema.com</p>";
    echo "<p><b>Senha:</b> admin123</p>";
    echo "<a href='index.php'>🚀 Acessar o Sistema</a>";
    echo "<br><br><small style='color:#888'>⚠️ Por segurança, exclua este arquivo (instalar.php) após a instalação.</small>";
} else {
    echo "<p style='border-left-color:#ef4444'>❌ Erro: " . $conn->error . "</p>";
}

$conn->close();
