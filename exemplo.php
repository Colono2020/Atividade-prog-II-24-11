<?php
// exemplo.php
// Demonstração de uso (seções 9 e 10 do artigo). Execução: php exemplo.php
declare(strict_types=1);

require_once __DIR__ . '/src/autoload.php';

use Biblioteca\Domain\Autor;
use Biblioteca\Domain\Livro;
use Biblioteca\Infrastructure\AutorRepository;
use Biblioteca\Infrastructure\Conexao;
use Biblioteca\Infrastructure\LivroRepository;

$pdo = Conexao::abrir();
$livros = new LivroRepository($pdo);
$autores = new AutorRepository($pdo);

// Verifica se o sql/schema.sql já foi executado (tabela N:N livro_autor)
$existe = $pdo->query("SELECT COUNT(*) FROM information_schema.tables
    WHERE table_schema = DATABASE() AND table_name = 'livro_autor'")->fetchColumn();
if ((int) $existe === 0) {
    fwrite(STDERR, "ERRO: a tabela livro_autor não existe. Execute primeiro o arquivo sql/schema.sql no MySQL Workbench.\n");
    exit(1);
}

// --- Seção 9: CRUD e associação ---
$autor = new Autor('Autora Exemplo', 'Brasileira', '1990-04-10');
$autores->salvar($autor);

$livro = new Livro('POO e Banco de Dados', null, 2026, 59.90, 3);
$livros->salvar($livro);
$livros->associarAutor($livro->id(), $autor->id());

$recuperado = $livros->buscar($livro->id());
foreach ($livros->autoresDoLivro($recuperado->id()) as $a) {
    $recuperado->adicionarAutor($a);
}
$recuperado->alterarTitulo('POO, PDO e MySQL');
$livros->salvar($recuperado);

echo "Livro #{$recuperado->id()}: {$recuperado->titulo()} ({$recuperado->anoPublicacao()})\n";
foreach ($recuperado->autores() as $a) {
    echo "  - Autor: {$a->nome()} ({$a->nacionalidade()})\n";
}

// --- Seção 10: transação ---
$pdo->beginTransaction();
try {
    $novo = new Livro('Título transacional');
    $livros->salvar($novo);
    $livros->associarAutor($novo->id(), $autor->id());
    $pdo->commit();
    echo "Transação confirmada: livro #{$novo->id()} gravado com autor.\n";
} catch (\Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}

// --- Demonstração de rollback: autor inexistente ---
$pdo->beginTransaction();
try {
    $falho = new Livro('Livro que não deve ficar');
    $livros->salvar($falho);
    $livros->associarAutor($falho->id(), 999999);
    $pdo->commit();
} catch (\PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $existe = $livros->buscar((int) $falho->id()) !== null ? 'SIM' : 'NÃO';
    echo "Rollback executado. O livro permaneceu no banco? $existe\n";
}

// --- Listagem: inclui os livros que já estavam no banco ---
echo "\nLivros cadastrados:\n";
foreach ($livros->listar() as $l) {
    $nomes = array_map(fn(Autor $a) => $a->nome(), $livros->autoresDoLivro($l->id()));
    $preco = $l->preco() === null ? '-' : 'R$ ' . number_format($l->preco(), 2, ',', '.');
    echo "  #{$l->id()} {$l->titulo()} | $preco | autores: " . ($nomes ? implode(', ', $nomes) : '(nenhum)') . "\n";
}
