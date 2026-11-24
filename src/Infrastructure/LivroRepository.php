<?php
// src/Infrastructure/LivroRepository.php
declare(strict_types=1);

namespace Biblioteca\Infrastructure;

use Biblioteca\Domain\Autor;
use Biblioteca\Domain\Livro;

/**
 * Repositório concreto de livros: especializa a classe abstrata.
 */
final class LivroRepository extends AbstractRepository
{
    protected function tabela(): string
    {
        return 'livro';
    }

    protected function colunas(): array
    {
        // id_autor (vínculo antigo 1:N) fica de fora de propósito: o ORM não o altera
        return ['titulo', 'isbn', 'ano_publicacao', 'preco', 'quantidade'];
    }

    protected function hidratar(array $linha): Livro
    {
        $livro = new Livro(
            $linha['titulo'],
            $linha['isbn'],
            $linha['ano_publicacao'] === null ? null : (int) $linha['ano_publicacao'],
            $linha['preco'] === null ? null : (float) $linha['preco'],
            $linha['quantidade'] === null ? null : (int) $linha['quantidade']
        );
        $livro->atribuirId((int) $linha['id']);
        return $livro;
    }

    protected function extrair(object $entidade): array
    {
        if (!$entidade instanceof Livro) {
            throw new \InvalidArgumentException('Esperado Livro');
        }
        return [
            'titulo' => $entidade->titulo(),
            'isbn' => $entidade->isbn(),
            'ano_publicacao' => $entidade->anoPublicacao(),
            'preco' => $entidade->preco(),
            'quantidade' => $entidade->quantidade(),
        ];
    }

    protected function identidade(object $entidade): ?int
    {
        if (!$entidade instanceof Livro) {
            throw new \InvalidArgumentException('Esperado Livro');
        }
        return $entidade->id();
    }

    protected function definirIdentidade(object $entidade, int $id): void
    {
        if (!$entidade instanceof Livro) {
            throw new \InvalidArgumentException('Esperado Livro');
        }
        $entidade->atribuirId($id);
    }

    /**
     * Carregamento explícito do relacionamento N:N (tabela livro_autor) (sem lazy loading).
     * @return Autor[]
     */
    public function autoresDoLivro(int $livroId): array
    {
        $sql = 'SELECT a.* FROM autor a
                INNER JOIN livro_autor la ON la.autor_id = a.id
                WHERE la.livro_id = :id
                ORDER BY a.nome';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $livroId]);
        return array_map(function (array $r) {
            $autor = new Autor($r['nome'], $r['nacionalidade'], $r['data_nascimento']);
            $autor->atribuirId((int) $r['id']);
            return $autor;
        }, $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function associarAutor(int $livroId, int $autorId): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO livro_autor (livro_id, autor_id) VALUES (:livro, :autor)'
        );
        $stmt->execute(['livro' => $livroId, 'autor' => $autorId]);
    }
}
