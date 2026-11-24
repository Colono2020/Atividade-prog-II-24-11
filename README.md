# BibliotecaORM — mini-ORM didático com PHP e MySQL

**UNOESC – Ciência da Computação | Programação II**
Professor: Leandro Otavio Cordova Vieira
Aluno: Alison Civiero

Implementação do tutorial "BibliotecaORM: desenvolvimento de um mapeador objeto-relacional didático com PHP e MySQL" (Aula 11), adaptada ao banco **`biblioteca`** criado no MySQL Workbench (tabelas `livro` e `autor`). O projeto segue o padrão **Data Mapper simplificado**: as entidades (`Livro`, `Autor`) representam o domínio e não conhecem o banco; os repositórios convertem registros em objetos e vice-versa; a classe abstrata concentra o CRUD comum.

## Estrutura

```
biblioteca-orm/
  sql/schema.sql                              # ajusta o banco biblioteca (cria livro_autor)
  src/autoload.php                            # autoloader (spl_autoload_register)
  src/Domain/Livro.php                        # entidade Livro
  src/Domain/Autor.php                        # entidade Autor
  src/Infrastructure/RepositoryInterface.php  # contrato polimórfico
  src/Infrastructure/AbstractRepository.php   # CRUD genérico (herança)
  src/Infrastructure/LivroRepository.php      # repositório de livros + N:N
  src/Infrastructure/AutorRepository.php      # repositório de autores + consultarPorContrato()
  src/Infrastructure/Conexao.php              # conexão PDO via variáveis de ambiente
  tests/smoke.php                             # testes da seção 11
  exemplo.php                                 # demonstração das seções 9 e 10
  README.md
```

## Requisitos

- PHP 8.2+ com a extensão `pdo_mysql` habilitada
- MySQL 8 (tabelas InnoDB)

## Como executar

1. Ajustar o banco: abra `sql/schema.sql` no MySQL Workbench e execute (⚡), ou rode:
   ```bash
   mysql -u root -p < sql/schema.sql
   ```
   O script **não apaga nada**: `autor` e `livro` já existem e são mantidas; ele só cria a tabela `livro_autor` (N:N) e copia para ela os vínculos que já estão em `livro.id_autor`.

2. Definir as variáveis de ambiente:

   Linux/macOS:
   ```bash
   export DB_HOST=127.0.0.1 DB_NAME=biblioteca DB_USER=root DB_PASS=sua_senha
   ```
   Windows (PowerShell):
   ```powershell
   $env:DB_HOST="127.0.0.1"; $env:DB_NAME="biblioteca"; $env:DB_USER="root"; $env:DB_PASS="sua_senha"
   ```

3. Rodar a demonstração e os testes:
   ```bash
   php exemplo.php
   php tests/smoke.php
   ```

> `tests/smoke.php` não apaga os dados existentes: ele registra o que cria e, no final, remove apenas esses registros de teste. O `exemplo.php` grava registros de demonstração de verdade ("Autora Exemplo", "POO, PDO e MySQL", "Título transacional").

## Adaptações em relação ao artigo

O artigo usa um banco `biblioteca_orm` com `livros`, `autores` e `livros_autores`. Este projeto usa o banco `biblioteca` já existente:

| Artigo | Este projeto |
|---|---|
| `livros` | `livro` (+ colunas `preco` e `quantidade`, mapeadas na entidade `Livro`) |
| `autores` | `autor` (+ coluna `data_nascimento`, mapeada na entidade `Autor`) |
| `livros_autores` | `livro_autor` (criada pelo `schema.sql`) |
| `exemplares` | não existe no banco; ficou fora (pode ser evolução) |

A coluna antiga `livro.id_autor` (relação 1:N) não entra na lista de colunas do `LivroRepository`; por isso o ORM nunca a sobrescreve. O relacionamento usado pelo código é o N:N de `livro_autor`.

## Conceitos de POO aplicados

| Conceito | Onde aparece |
|---|---|
| Encapsulamento | Propriedades `private` em `Livro`/`Autor`; alterações só por métodos que validam (`alterarTitulo`, `atribuirId`). |
| Herança | `LivroRepository` e `AutorRepository` estendem `AbstractRepository`. |
| Abstração | `AbstractRepository` define métodos abstratos (`tabela`, `colunas`, `hidratar`, `extrair`...); o consumidor não escreve SQL. |
| Interface / Polimorfismo | `RepositoryInterface`; `consultarPorContrato()` funciona com qualquer repositório. |
| Associação N:N | `Livro` tem uma coleção de `Autor`; persistida em `livro_autor` (`associarAutor`, `autoresDoLivro`). |
| Persistência / PDO | Consultas preparadas com parâmetros nomeados; `ERRMODE_EXCEPTION`; `EMULATE_PREPARES = false`. |
| Transações | `beginTransaction` / `commit` / `rollBack` no `exemplo.php` e nos testes. |

## Testes (seção 11)

O `tests/smoke.php` cobre todos os casos pedidos:

1. Inserção e ID gerado
2. Busca existente e inexistente
3. Alteração de título (UPDATE)
4. Exclusão
5. Autor com livro (N:N)
6. FK inválida e rollback (o livro não permanece no banco) + transação válida com commit
7. Rejeição de título vazio
8. Tentativa de SQL injection em campo textual (gravada como texto literal)
9. Contrato polimórfico
10. Duplicação de vínculo N:N (rejeitada pela chave primária composta; o domínio também ignora autor repetido)
11. Exclusão de autor vinculado (rejeitada pela FK, pois não há `ON DELETE CASCADE`)
12. Identidade não pode ser trocada depois de atribuída
13. Preço negativo e data de nascimento inválida são rejeitados

Saída esperada:
```
[OK]    Inserção gera ID
...
Limpeza: 15 registros de teste removidos (seus dados originais não foram alterados).
Resultado: 14 passaram, 0 falharam.
```

## Limites da implementação

- Só funciona com tabelas que têm chave primária inteira chamada `id`.
- `salvar()` decide INSERT ou UPDATE apenas pela existência do ID; não detecta concorrência nem exclusão feita por outro processo (sem lock otimista).
- O banco tem `id_autor` (1:N) e `livro_autor` (N:N) ao mesmo tempo; o ORM só mantém o N:N. Os dois podem ficar diferentes se alguém alterar `id_autor` direto no Workbench.
- A coluna `isbn` não é `UNIQUE` no banco (os livros 7 e 8 estão duplicados), então o banco não impede ISBN repetido.
- Relacionamentos são carregados de forma explícita (`autoresDoLivro`); não há lazy loading, identity map, migrations nem query builder.
- Não há exclusão em cascata automática.

## Reflexão crítica

O mini-ORM mostra como os princípios de POO organizam uma solução real: o domínio protege suas regras (título obrigatório, identidade imutável); a classe abstrata reaproveita o algoritmo de CRUD; as classes concretas só informam o que muda (tabela, colunas e hidratação); o PDO garante parâmetros preparados; e as transações junto com as chaves estrangeiras mantêm o banco consistente.

A generalização tem custo. Um mapeador muito genérico pode esconder SQL ineficiente — por exemplo, chamar `autoresDoLivro()` dentro de um laço sobre `listar()` gera o problema **N+1** (uma consulta por livro). Também dificulta a depuração, porque o SQL não fica visível para quem usa o repositório. Por isso, usar um ORM não dispensa conhecer SQL, normalização, índices e transações.

Como evolução, seria interessante: comparar Data Mapper com Active Record; carregar os autores de vários livros em lote (um único `JOIN` com `IN (...)`); criar um `ExemplarRepository`; usar PHPUnit para testes automatizados; e criar uma camada de serviço responsável por orquestrar as operações transacionais.

## Referências

- FOWLER, Martin. *Patterns of Enterprise Application Architecture*. Boston: Addison-Wesley, 2002.
- PHP DOCUMENTATION GROUP. PHP: PDO. https://www.php.net/manual/pt_BR/book.pdo.php
- PHP DOCUMENTATION GROUP. PHP: PDO::prepare. https://www.php.net/manual/pt_BR/pdo.prepare.php
- PHP DOCUMENTATION GROUP. PHP: Object Interfaces. https://www.php.net/manual/en/language.oop5.interfaces.php
- ORACLE. MySQL 8.4 Reference Manual. https://dev.mysql.com/doc/refman/8.4/en/
