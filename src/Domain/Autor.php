<?php
// src/Domain/Autor.php
declare(strict_types=1);

namespace Biblioteca\Domain;

/**
 * Entidade de domínio Autor (tabela `autor`).
 */
final class Autor
{
    private ?int $id = null;
    private string $nome;
    private ?string $nacionalidade;
    private ?string $dataNascimento;

    /** @param string|null $dataNascimento formato AAAA-MM-DD */
    public function __construct(string $nome, ?string $nacionalidade = null, ?string $dataNascimento = null)
    {
        if (trim($nome) === '') {
            throw new \InvalidArgumentException('Nome obrigatório');
        }
        if ($dataNascimento !== null) {
            $data = \DateTimeImmutable::createFromFormat('!Y-m-d', $dataNascimento);
            if ($data === false || $data->format('Y-m-d') !== $dataNascimento) {
                throw new \InvalidArgumentException('Data de nascimento inválida (use AAAA-MM-DD)');
            }
        }
        $this->nome = trim($nome);
        $this->nacionalidade = $nacionalidade;
        $this->dataNascimento = $dataNascimento;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function atribuirId(int $id): void
    {
        if ($id <= 0 || ($this->id !== null && $this->id !== $id)) {
            throw new \LogicException('Identidade inválida');
        }
        $this->id = $id;
    }

    public function nome(): string
    {
        return $this->nome;
    }

    public function nacionalidade(): ?string
    {
        return $this->nacionalidade;
    }

    public function dataNascimento(): ?string
    {
        return $this->dataNascimento;
    }
}
