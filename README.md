# Clinix

Clinix é uma aplicação web para gestão de pacientes, agendamentos e atendimento de recepção, com fluxo de login para paciente e recepcionista.

## Objetivo

Centralizar a experiência de agendamento, acompanhamento e administração de consultas em um sistema orientado a web, mantendo a navegação e os processos já existentes em HTML, CSS, JavaScript e PHP.

## Tecnologias utilizadas

- HTML5
- CSS3
- JavaScript (vanilla)
- PHP
- SQL Server via PDO (driver `sqlsrv`)
- PHPMailer
- Twilio (para mensagens/WhatsApp)

## Estrutura principal do projeto

```text
Clinix/
├── index.html
├── README.md
├── .gitignore
├── web.config
├── backend/
│   ├── config.php
│   ├── login_paciente.php
│   ├── login_recepcao.php
│   ├── ...
│   └── PHPMailer/
├── pages/
│   ├── Login_Paciente.html
│   ├── Cadastro_Paciente.html
│   ├── Recuperacao_de_Senha.html
│   ├── Area_Paciente.html
│   ├── Agendamento_Paciente.html
│   ├── Notificacoes_Paciente.html
│   ├── Perfil_Paciente.html
│   ├── Login_Recepcao.html
│   ├── Area_Recepcao_Consultas.html
│   ├── Area_Recepcao_Agendamento.html
│   └── Area_Recepcao_Alteracao_Consulta.html
├── assets/
│   └── icons/
│       ├── logo.png
│       ├── notificacao.png
│       ├── icon_editar.png
│       ├── icon_deletar.png
│       └── icon_perfil.png
├── teste.php
├── teste_conexao.php
└── backend/
```

## Como executar localmente

1. Coloque a pasta do projeto em um ambiente com PHP habilitado, como XAMPP, WAMP ou IIS.
2. Certifique-se de que a extensão `pdo_sqlsrv` esteja disponível no ambiente PHP.
3. Ajuste as configurações do banco e das integrações no arquivo `backend/config.php`, caso necessário.
4. Acesse o projeto por meio do arquivo raiz `index.html` ou diretamente em `pages/Login_Paciente.html`.

## Observações

- O projeto já foi organizado para manter a navegação atual e os recursos visuais principais intactos.
- A estrutura foi ajustada para facilitar versionamento em repositório GitHub sem alterar o funcionamento do sistema.
