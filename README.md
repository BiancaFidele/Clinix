# 🩺 Clinix — Sistema de Agendamento de Consultas

O **Clinix** é um sistema acadêmico de agendamento de consultas desenvolvido durante o curso de **Análise e Desenvolvimento de Sistemas da FATEC Taquaritinga**.

O projeto foi criado com o objetivo de aplicar conceitos de **desenvolvimento web, banco de dados relacional, SQL, modelagem de dados e análise de sistemas**.

## 📌 Sobre o projeto

O Clinix simula o funcionamento de um sistema para gerenciamento de consultas médicas, permitindo organizar informações relacionadas a pacientes, médicos, especialidades e agendamentos.

Além do agendamento de consultas, o banco foi estruturado para contemplar situações como pré-reservas, cancelamentos, remarcações, histórico de consultas, envio de mensagens e períodos de indisponibilidade dos médicos.

## 🗄️ Banco de Dados

O banco de dados foi desenvolvido utilizando **Microsoft SQL Server**.

A estrutura possui as seguintes tabelas principais:

* `especialidades` — cadastro das especialidades médicas;
* `paciente` — informações cadastrais e de acesso dos pacientes;
* `recepcionistas` — dados dos responsáveis pelo atendimento;
* `medicos` — cadastro dos médicos e relacionamento com suas especialidades;
* `consultas` — gerenciamento dos agendamentos;
* `pre_reservas` — controle temporário de horários antes da confirmação;
* `historico_consultas` — registro de cancelamentos, remarcações e alterações;
* `mensagens_enviadas` — controle das comunicações relacionadas às consultas;
* `feriados` — armazenamento de datas sem atendimento;
* `medicos_inativos` — controle dos períodos de indisponibilidade dos médicos.

## 🔗 Modelagem Relacional

O banco utiliza relacionamentos entre suas tabelas por meio de **Primary Keys** e **Foreign Keys**.

Entre os principais relacionamentos estão:

* Especialidade → Médicos
* Paciente → Consultas
* Médico → Consultas
* Paciente → Pré-reservas
* Médico → Pré-reservas
* Consulta → Histórico de consultas
* Consulta → Mensagens enviadas
* Médico → Períodos de inatividade

Também foram utilizadas restrições `CHECK` para controlar determinados valores permitidos no sistema, como:

* origem da criação da consulta;
* status da consulta;
* tipo de alteração no histórico;
* tipo de mensagem enviada.

## ⚙️ Funcionalidades representadas no banco

A estrutura do banco permite representar processos como:

* Cadastro de pacientes;
* Cadastro de médicos e especialidades;
* Cadastro de recepcionistas;
* Agendamento de consultas;
* Pré-reserva de horários;
* Cancelamento e remarcação de consultas;
* Registro do histórico de alterações;
* Controle de status das consultas;
* Registro de notificações enviadas;
* Controle de feriados;
* Controle de períodos de indisponibilidade médica.

## 💻 Tecnologias utilizadas

* HTML
* CSS
* SQL
* Microsoft SQL Server
* UML

## 🎯 Conhecimentos aplicados

Durante o desenvolvimento do projeto foram utilizados conceitos de:

* Banco de dados relacional;
* Modelagem de dados;
* SQL;
* Primary Keys e Foreign Keys;
* Integridade referencial;
* Restrições `CHECK`;
* Relacionamentos entre entidades;
* Análise de sistemas;
* Desenvolvimento de interfaces web;
* Diagramas UML.

## 📚 Contexto acadêmico

Projeto desenvolvido em **2025** durante o curso de **Tecnologia em Análise e Desenvolvimento de Sistemas — FATEC Taquaritinga**.

O Clinix faz parte do meu portfólio acadêmico e demonstra a aplicação prática de conhecimentos relacionados principalmente a **Banco de Dados, SQL e Análise de Sistemas**.

## 👩‍💻 Autora

**Bianca Fidele**

Estudante de Análise e Desenvolvimento de Sistemas, com foco em **Análise de Dados, Business Intelligence e Banco de Dados**.
