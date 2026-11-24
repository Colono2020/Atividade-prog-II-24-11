<?php
// src/Infrastructure/AutorRepository.php
declare(strict_types=1);

namespace Biblioteca\Infrastructure;

use Biblioteca\Domain\Autor;

/**
 * Repositório concreto de autores.
 */
final class AutorRepository extends AbstractRepository
{
    protected function tabela(): string
    {
        return 'autor';
    }

    protected function colunas(): array
    {
        return ['nome', 'nacionalidade', 'data_nascimento'];
    }

    protected function hidratar(array $r): Autor
    {
        $a = new Autor($r['nome'], $r['nacionalidade'], $r['data_nascimento']);
        $a->atribuirId((int) $r['id']);
        return $a;
    }

    protected function extrair(object $e): array
    {
        if (!$e instanceof Autor) {
            throw new \InvalidArgumentException('Esperado Autor');
        }
        return [
            'nome' => $e->nome(),
            'nacionalidade' => $e->nacionalidade(),
            'data_nascimento' => $e->dataNascimento(),
        ];
    }

    protected function identidade(object $e): ?int
    {
        if (!$e instanceof Autor) {
            throw new \InvalidArgumentException('Esperado Autor');
        }
        return $e->id();
    }

    protected function definirIdentidade(object $e, int $id): void
    {
        if (!$e instanceof Autor) {
            throw new \InvalidArgumentException('Esperado Autor');
        }
        $e->atribuirId($id);
    }
}

/**
 * Polimorfismo efetivo: funciona com qualquer repositório que cumpra o contrato.
 */
function consultarPorContrato(RepositoryInterface $repo, int $id): ?object
{
    return $repo->buscar($id);
}
