<?php
// src/Infrastructure/RepositoryInterface.php
declare(strict_types=1);

namespace Biblioteca\Infrastructure;

/**
 * Contrato polimórfico comum a todos os repositórios.
 */
interface RepositoryInterface
{
    public function buscar(int $id): ?object;

    public function listar(): array;

    public function salvar(object $entidade): void;

    public function excluir(int $id): void;
}
