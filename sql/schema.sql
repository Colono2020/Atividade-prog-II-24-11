-- sql/schema.sql
-- Banco "biblioteca" (o mesmo criado no MySQL Workbench) adaptado ao mini-ORM.
--
-- Pode rodar no banco que já existe: as tabelas autor e livro NÃO são recriadas
-- (IF NOT EXISTS) e nenhum dado é apagado. O script só cria a tabela livro_autor,
-- que faz o relacionamento N:N pedido no artigo, e copia para ela os vínculos que
-- já existem na coluna livro.id_autor.
CREATE DATABASE IF NOT EXISTS biblioteca CHARACTER SET utf8mb4;
USE biblioteca;

CREATE TABLE IF NOT EXISTS autor (
    id INT NOT NULL AUTO_INCREMENT,
    nome VARCHAR(100) NOT NULL,
    nacionalidade VARCHAR(50) DEFAULT NULL,
    data_nascimento DATE DEFAULT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS livro (
    id INT NOT NULL AUTO_INCREMENT,
    titulo VARCHAR(150) NOT NULL,
    isbn VARCHAR(20) DEFAULT NULL,
    ano_publicacao INT DEFAULT NULL,
    preco DECIMAL(10,2) DEFAULT NULL,
    quantidade INT DEFAULT NULL,
    id_autor INT DEFAULT NULL,
    PRIMARY KEY (id),
    KEY fk_livro_autor (id_autor),
    CONSTRAINT fk_livro_autor FOREIGN KEY (id_autor) REFERENCES autor (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- NOVA: relacionamento N:N entre livro e autor (sem ON DELETE CASCADE, como no artigo)
CREATE TABLE IF NOT EXISTS livro_autor (
    livro_id INT NOT NULL,
    autor_id INT NOT NULL,
    PRIMARY KEY (livro_id, autor_id),
    CONSTRAINT fk_la_livro FOREIGN KEY (livro_id) REFERENCES livro (id),
    CONSTRAINT fk_la_autor FOREIGN KEY (autor_id) REFERENCES autor (id)
) ENGINE=InnoDB;

-- Copia os vínculos antigos (livro.id_autor) para a tabela N:N
INSERT IGNORE INTO livro_autor (livro_id, autor_id)
SELECT id, id_autor FROM livro WHERE id_autor IS NOT NULL;
