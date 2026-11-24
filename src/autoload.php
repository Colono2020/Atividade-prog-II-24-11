<?php
// src/autoload.php
// Autoloader próprio (spl_autoload_register) para o namespace Biblioteca\
declare(strict_types=1);

spl_autoload_register(function (string $classe): void {
    $prefixo = 'Biblioteca\\';
    if (strncmp($classe, $prefixo, strlen($prefixo)) !== 0) {
        return;
    }
    $relativo = substr($classe, strlen($prefixo));
    $arquivo = __DIR__ . '/' . str_replace('\\', '/', $relativo) . '.php';
    if (is_file($arquivo)) {
        require_once $arquivo;
    }
});

// A função consultarPorContrato() fica em AutorRepository.php; funções não são
// carregadas por autoload, então o arquivo é incluído explicitamente.
require_once __DIR__ . '/Infrastructure/AutorRepository.php';
