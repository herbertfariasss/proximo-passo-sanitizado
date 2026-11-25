-- Execute uma vez em bancos já existentes.
ALTER TABLE objetivos ADD COLUMN cancelado_em DATETIME NULL;
ALTER TABLE tarefas ADD COLUMN cancelado_em DATETIME NULL;
