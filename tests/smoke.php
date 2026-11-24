<?php
// tests/smoke.php
// Testes reproduzíveis do mini-ORM (seção 11 do artigo).
// O teste NÃO apaga os seus dados: ele anota tudo o que cria e remove só isso no final.
// Execução:  php tests/smoke.php
declare(strict_types=1);

require_once __DIR__ . '/../src/autoload.php';

use Biblioteca\Domain\Autor;
use Biblioteca\Domain\Livro;
use Biblioteca\Infrastructure\AutorRepository;
use Biblioteca\Infrastructure\Conexao;
use Biblioteca\Infrastructure\LivroRepository;
use function Biblioteca\Infrastructure\consultarPorContrato;

$pdo = $pdo ?? Conexao::abrir();
$livros = new LivroRepository($pdo);
$autores = new AutorRepository($pdo);

// Verifica se o sql/schema.sql já foi executado (tabela N:N livro_autor)
$existe = $pdo->query("SELECT COUNT(*) FROM information_schema.tables
    WHERE table_schema = DATABASE() AND table_name = 'livro_autor'")->fetchColumn();
if ((int) $existe === 0) {
    fwrite(STDERR, "ERRO: a tabela livro_autor não existe. Execute primeiro o arquivo sql/schema.sql no MySQL Workbench.\n");
    exit(1);
}

$ok = 0;
$falhas = 0;

function teste(string $nome, callable $fn): void
{
    global $ok, $falhas;
    try {
        $fn();
        $ok++;
        echo "[OK]    $nome\n";
    } catch (\Throwable $e) {
        $falhas++;
        echo "[FALHA] $nome -> " . get_class($e) . ': ' . $e->getMessage() . "\n";
    }
}

function confirmar(bool $condicao, string $mensagem): void
{
    if (!$condicao) {
        throw new \RuntimeException($mensagem);
    }
}

function contarLivros(\PDO $pdo): int
{
    return (int) $pdo->query('SELECT COUNT(*) FROM livro')->fetchColumn();
}

// Registro do que o teste cria, para limpar no final sem tocar nos dados existentes
$criados = ['livro' => [], 'autor' => []];
function novoLivro(LivroRepository $repo, Livro $l): Livro
{
    global $criados;
    $repo->salvar($l);
    $criados['livro'][] = $l->id();
    return $l;
}
function novoAutor(AutorRepository $repo, Autor $a): Autor
{
    global $criados;
    $repo->salvar($a);
    $criados['autor'][] = $a->id();
    return $a;
}

// 1. Inserção e ID gerado
teste('Inserção gera ID', function () use ($livros) {
    $l = new Livro('Dom Casmurro', '9788535910', 1899, 39.90, 2);
    confirmar($l->id() === null, 'Livro novo não deveria ter ID');
    novoLivro($livros, $l);
    confirmar(is_int($l->id()) && $l->id() > 0, 'ID não foi atribuído após INSERT');
});

// 2. Busca existente / inexistente
teste('Busca existente e inexistente', function () use ($livros) {
    $l = novoLivro($livros, new Livro('Memórias Póstumas de Brás Cubas', null, 1881, 25.50, 4));
    $achado = $livros->buscar($l->id());
    confirmar($achado instanceof Livro, 'Livro existente não encontrado');
    confirmar($achado->titulo() === 'Memórias Póstumas de Brás Cubas', 'Título hidratado incorreto');
    confirmar($achado->anoPublicacao() === 1881, 'Ano hidratado incorreto');
    confirmar($achado->preco() === 25.5 && $achado->quantidade() === 4, 'Preço/quantidade incorretos');
    confirmar($livros->buscar(999999) === null, 'Busca inexistente deveria retornar null');
});

// 3. Alteração de título (UPDATE)
teste('Alteração de título', function () use ($livros) {
    $l = novoLivro($livros, new Livro('Título antigo'));
    $id = $l->id();
    $l->alterarTitulo('Título novo');
    $livros->salvar($l);
    confirmar($l->id() === $id, 'UPDATE não deveria trocar o ID');
    confirmar($livros->buscar($id)->titulo() === 'Título novo', 'Título não foi atualizado');
});

// 4. Exclusão
teste('Exclusão', function () use ($livros) {
    $l = novoLivro($livros, new Livro('Livro para excluir'));
    $livros->excluir($l->id());
    confirmar($livros->buscar($l->id()) === null, 'Livro não foi excluído');
});

// 5. Autor com livro (relacionamento N:N carregado explicitamente)
teste('Autor associado ao livro', function () use ($livros, $autores) {
    $a1 = novoAutor($autores, new Autor('Machado de Assis', 'Brasileira', '1839-06-21'));
    $a2 = novoAutor($autores, new Autor('Clarice Lispector', 'Brasileira', '1920-12-10'));
    $l = novoLivro($livros, new Livro('Antologia'));
    $livros->associarAutor($l->id(), $a1->id());
    $livros->associarAutor($l->id(), $a2->id());

    $recuperado = $livros->buscar($l->id());
    foreach ($livros->autoresDoLivro($recuperado->id()) as $a) {
        $recuperado->adicionarAutor($a);
    }
    $nomes = array_map(fn(Autor $a) => $a->nome(), $recuperado->autores());
    confirmar($nomes === ['Clarice Lispector', 'Machado de Assis'], 'Autores carregados incorretamente');
    confirmar($recuperado->autores()[1]->dataNascimento() === '1839-06-21', 'Data de nascimento incorreta');
});

// 6. FK inválida dentro de transação -> rollback
teste('FK inválida provoca rollback', function () use ($pdo, $livros) {
    $antes = contarLivros($pdo);
    $falhou = false;
    $pdo->beginTransaction();
    try {
        $novo = new Livro('Título transacional');
        $livros->salvar($novo);
        $livros->associarAutor($novo->id(), 999999); // autor inexistente
        $pdo->commit();
    } catch (\PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $falhou = true;
    }
    confirmar($falhou, 'Associação com autor inexistente deveria falhar');
    confirmar(contarLivros($pdo) === $antes, 'Livro permaneceu no banco após rollback');
});

// 6b. Transação válida -> commit
teste('Transação válida faz commit', function () use ($pdo, $livros, $autores) {
    $autor = novoAutor($autores, new Autor('Autora Transacional', 'Brasileira'));
    $pdo->beginTransaction();
    try {
        $novo = novoLivro($livros, new Livro('Livro com commit'));
        $livros->associarAutor($novo->id(), $autor->id());
        $pdo->commit();
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
    confirmar(count($livros->autoresDoLivro($novo->id())) === 1, 'Vínculo não persistido');
});

// 7. Rejeição de título vazio
teste('Título vazio é rejeitado', function () {
    try {
        new Livro('   ');
    } catch (\InvalidArgumentException $e) {
        return;
    }
    throw new \RuntimeException('Título vazio foi aceito');
});

// 8. Tentativa de SQL injection em campo textual
teste('SQL injection é tratada como texto', function () use ($pdo, $livros) {
    $malicioso = "x'); DROP TABLE livros; -- ";
    $l = novoLivro($livros, new Livro($malicioso));
    confirmar($livros->buscar($l->id())->titulo() === trim($malicioso), 'Texto não gravado literalmente');
    confirmar(contarLivros($pdo) > 0, 'Tabela livros foi afetada');
});

// 9. Contrato polimórfico
teste('Contrato polimórfico (RepositoryInterface)', function () use ($livros, $autores) {
    $l = novoLivro($livros, new Livro('Polimorfismo'));
    $a = novoAutor($autores, new Autor('Autor Polimórfico'));
    confirmar(consultarPorContrato($livros, $l->id()) instanceof Livro, 'Esperado Livro');
    confirmar(consultarPorContrato($autores, $a->id()) instanceof Autor, 'Esperado Autor');
});

// 10. Duplicação de vínculo N:N
teste('Vínculo N:N duplicado é rejeitado', function () use ($livros, $autores) {
    $a = novoAutor($autores, new Autor('Autor Duplicado'));
    $l = novoLivro($livros, new Livro('Livro Duplicado'));
    $livros->associarAutor($l->id(), $a->id());
    try {
        $livros->associarAutor($l->id(), $a->id());
    } catch (\PDOException $e) {
        // No domínio, adicionarAutor() também ignora o autor repetido
        $l->adicionarAutor($a);
        $l->adicionarAutor($a);
        confirmar(count($l->autores()) === 1, 'Domínio aceitou autor duplicado');
        return;
    }
    throw new \RuntimeException('Banco aceitou vínculo duplicado');
});

// 11. Exclusão de autor vinculado (sem ON DELETE CASCADE)
teste('Excluir autor vinculado é rejeitado pela FK', function () use ($livros, $autores) {
    $a = novoAutor($autores, new Autor('Autor Vinculado'));
    $l = novoLivro($livros, new Livro('Livro Vinculado'));
    $livros->associarAutor($l->id(), $a->id());
    try {
        $autores->excluir($a->id());
    } catch (\PDOException $e) {
        confirmar($autores->buscar($a->id()) !== null, 'Autor sumiu mesmo com erro');
        return;
    }
    throw new \RuntimeException('Autor vinculado foi excluído');
});

// 13. Validações extras do domínio (preço negativo e data inválida)
teste('Preço negativo e data inválida são rejeitados', function () {
    $rejeitados = 0;
    try { new Livro('X', null, null, -1.0); } catch (\InvalidArgumentException $e) { $rejeitados++; }
    try { new Autor('Y', null, '2026-02-31'); } catch (\InvalidArgumentException $e) { $rejeitados++; }
    confirmar($rejeitados === 2, 'Valor inválido foi aceito');
});

// 12. Identidade não pode ser trocada
teste('Identidade não pode ser alterada', function () {
    $l = new Livro('Identidade');
    $l->atribuirId(10);
    try {
        $l->atribuirId(20);
    } catch (\LogicException $e) {
        return;
    }
    throw new \RuntimeException('ID foi trocado');
});

// Limpeza: remove só o que este teste criou (vínculos primeiro, por causa das FKs)
$removidos = 0;
foreach ($criados['livro'] as $id) {
    $pdo->prepare('DELETE FROM livro_autor WHERE livro_id = :id')->execute(['id' => $id]);
}
foreach ($criados['autor'] as $id) {
    $pdo->prepare('DELETE FROM livro_autor WHERE autor_id = :id')->execute(['id' => $id]);
}
foreach (['livro', 'autor'] as $tabela) {
    foreach ($criados[$tabela] as $id) {
        $stmt = $pdo->prepare("DELETE FROM $tabela WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $removidos += $stmt->rowCount();
    }
}
echo "\nLimpeza: $removidos registros de teste removidos (seus dados originais não foram alterados).\n";
echo "Resultado: $ok passaram, $falhas falharam.\n";
exit($falhas === 0 ? 0 : 1);
