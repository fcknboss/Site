# Eskort - Sistema de Gerenciamento de Perfis

Bem-vindo ao Eskort, um sistema web para gerenciamento de perfis de acompanhantes e criadoras, desenvolvido com PHP, MySQL, HTML5, CSS e WebSocket. Inspirado em plataformas como Facebook, RedGIFs e Boobpedia, o sistema oferece um painel de administração robusto com funcionalidades como filtros, exportação de dados, moderação de fotos e notificações em tempo real.

## Requisitos

- **XAMPP**: Versão 7.4+ (PHP, Apache, MySQL).
- **MySQL**: Banco de dados configurado.
- **Composer**: Para instalar dependências do WebSocket (Ratchet).
- **FFmpeg**: Para gerar thumbnails de vídeos (opcional, usado em `edit_escort.php`).

## Instalação

1. **Clone o Repositório**:
   ```bash
   git clone https://github.com/fcknboss/Site.git C:\xampp\htdocs\eskort\Site
   cd C:\xampp\htdocs\eskort\Site