<?php
// src/Domain/Livro.php
declare(strict_types=1);

namespace Biblioteca\Domain;

/**
 * Entidade de domínio Livro (tabela `livro`).
 * Encapsulamento: o estado só muda por métodos que verificam invariantes.
 * Associação: um livro referencia uma coleção de autores (N:N em `livro_autor`).
 */
final class Livro
{
    private ?int $id = null;
    private string $titulo;
    private ?string $isbn;
    private ?int $anoPublicacao;
    private ?float $preco = null;
    private ?int $quantidade = null;
    /** @var Autor[] */
    private array $autores = [];

    public function __construct(
        string $titulo,
        ?string $isbn = null,
        ?int $ano = null,
        ?float $preco = null,
        ?int $quantidade = null
    ) {
        $this->alterarTitulo($titulo);
        $this->isbn = $isbn;
        $this->anoPublicacao = $ano;
        $this->alterarPreco($preco);
        $this->alterarQuantidade($quantidade);
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

    public function titulo(): string
    {
        return $this->titulo;
    }

    public function isbn(): ?string
    {
        return $this->isbn;
    }

    public function anoPublicacao(): ?int
    {
        return $this->anoPublicacao;
    }

    public function preco(): ?float
    {
        return $this->preco;
    }

    public function quantidade(): ?int
    {
        return $this->quantidade;
    }

    public function alterarTitulo(string $titulo): void
    {
        if (trim($titulo) === '') {
            throw new \InvalidArgumentException('Título obrigatório');
        }
        $this->titulo = trim($titulo);
    }

    public function alterarPreco(?float $preco): void
    {
        if ($preco !== null && $preco < 0) {
            throw new \InvalidArgumentException('Preço não pode ser negativo');
        }
        $this->preco = $preco;
    }

    public function alterarQuantidade(?int $quantidade): void
    {
        if ($quantidade !== null && $quantidade < 0) {
            throw new \InvalidArgumentException('Quantidade não pode ser negativa');
        }
        $this->quantidade = $quantidade;
    }

    public function adicionarAutor(Autor $autor): void
    {
        foreach ($this->autores as $existente) {
            if ($existente === $autor || ($autor->id() !== null && $existente->id() === $autor->id())) {
                return; // evita autor duplicado na coleção
            }
        }
        $this->autores[] = $autor;
    }

    /** @return Autor[] */
    public function autores(): array
    {
        return $this->autores;
    }
}
