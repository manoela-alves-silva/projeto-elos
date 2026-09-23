<?php

declare(strict_types=1);

use Elos\Config\Database;
use Elos\Controllers\AgendaEventoController;
use Elos\Controllers\AnexoController;
use Elos\Controllers\CategoriaTarefaController;
use Elos\Controllers\CursoController;
use Elos\Controllers\EtapaController;
use Elos\Controllers\EventoController;
use Elos\Controllers\EventoCursoController;
use Elos\Controllers\FormularioController;
use Elos\Controllers\HistoricoController;
use Elos\Controllers\LocalController;
use Elos\Controllers\ResponsavelController;
use Elos\Controllers\TarefaController;
use Elos\Controllers\TipoEventoController;
use Elos\Controllers\TransporteController;
use Elos\Controllers\UsuarioController;
use Elos\Controllers\VisitaController;
use Elos\Repositories\AgendaEventoRepository;
use Elos\Repositories\AnexoRepository;
use Elos\Repositories\CategoriaTarefaRepository;
use Elos\Repositories\CursoRepository;
use Elos\Repositories\EtapaRepository;
use Elos\Repositories\EventoCursoRepository;
use Elos\Repositories\EventoRepository;
use Elos\Repositories\FormularioRepository;
use Elos\Repositories\HistoricoRepository;
use Elos\Repositories\LocalRepository;
use Elos\Repositories\ResponsavelRepository;
use Elos\Repositories\TarefaRepository;
use Elos\Repositories\TipoEventoRepository;
use Elos\Repositories\TransporteRepository;
use Elos\Repositories\UsuarioRepository;
use Elos\Repositories\VisitaRepository;
use Elos\Services\AuthService;
use Elos\Services\AuthorizationService;
use Elos\Services\HistoricoService;
use Elos\Services\SessionService;

// Datas no horário de Brasília (o padrão do PHP é UTC).
date_default_timezone_set('America/Sao_Paulo');

// Detalhes de erro só na tela com ELOS_DEBUG=1 (desenvolvimento).
// Sem isso eles vão para o log/terminal, nunca para quem usa o site.
ini_set('display_errors', getenv('ELOS_DEBUG') === '1' ? '1' : '0');
ini_set('log_errors', '1');

require_once dirname(__DIR__) . '/src/bootstrap.php';

$database = Database::fromEnvironment();
$pdo = $database->getConnection();

$sessionService = new SessionService();

// Histórico automático: as rotas registram quem fez cada alteração.
HistoricoService::ativar(new HistoricoService(new HistoricoRepository($pdo), $sessionService));

$usuarioControllerFactory = static function () use ($pdo, $sessionService): UsuarioController {
    $usuarioRepository = new UsuarioRepository($pdo);

    return new UsuarioController(
        $usuarioRepository,
        new AuthService($usuarioRepository),
        $sessionService
    );
};

$tipoEventoControllerFactory = static function () use ($pdo): TipoEventoController {
    return new TipoEventoController(
        new TipoEventoRepository($pdo)
    );
};

$responsavelControllerFactory = static function () use ($pdo): ResponsavelController {
    return new ResponsavelController(
        new ResponsavelRepository($pdo)
    );
};

$cursoControllerFactory = static function () use ($pdo): CursoController {
    return new CursoController(
        new CursoRepository($pdo)
    );
};

$localControllerFactory = static function () use ($pdo): LocalController {
    return new LocalController(
        new LocalRepository($pdo)
    );
};

$eventoControllerFactory = static function () use ($pdo): EventoController {
    return new EventoController(
        new EventoRepository($pdo)
    );
};

$eventoCursoControllerFactory = static function () use ($pdo): EventoCursoController {
    return new EventoCursoController(
        new EventoCursoRepository($pdo)
    );
};

$agendaEventoControllerFactory = static function () use ($pdo): AgendaEventoController {
    return new AgendaEventoController(
        new AgendaEventoRepository($pdo)
    );
};

$etapaControllerFactory = static function () use ($pdo): EtapaController {
    return new EtapaController(
        new EtapaRepository($pdo)
    );
};

$categoriaTarefaControllerFactory = static function () use ($pdo): CategoriaTarefaController {
    return new CategoriaTarefaController(
        new CategoriaTarefaRepository($pdo)
    );
};

$tarefaControllerFactory = static function () use ($pdo): TarefaController {
    return new TarefaController(
        new TarefaRepository($pdo)
    );
};

$formularioControllerFactory = static function () use ($pdo): FormularioController {
    return new FormularioController(
        new FormularioRepository($pdo)
    );
};

$transporteControllerFactory = static function () use ($pdo): TransporteController {
    return new TransporteController(
        new TransporteRepository($pdo)
    );
};

$visitaControllerFactory = static function () use ($pdo): VisitaController {
    return new VisitaController(
        new VisitaRepository($pdo)
    );
};

$anexoControllerFactory = static function () use ($pdo): AnexoController {
    return new AnexoController(
        new AnexoRepository($pdo)
    );
};

$historicoControllerFactory = static function () use ($pdo): HistoricoController {
    return new HistoricoController(
        new HistoricoRepository($pdo)
    );
};

$authorizationFactory = static function () use ($sessionService): AuthorizationService {
    return new AuthorizationService($sessionService);
};

// Perfil e situação da conta sempre conforme o banco (uma troca feita
// na tela Equipe vale sem precisar sair e entrar de novo).
$usuarioControllerFactory()->sincronizarSessao();

require dirname(__DIR__) . '/routes/api.php';

handleApiRequest(
    $usuarioControllerFactory,
    $authorizationFactory,
    $tipoEventoControllerFactory,
    $responsavelControllerFactory,
    $cursoControllerFactory,
    $localControllerFactory,
    $eventoControllerFactory,
    $eventoCursoControllerFactory,
    $agendaEventoControllerFactory,
    $etapaControllerFactory,
    $categoriaTarefaControllerFactory,
    $tarefaControllerFactory,
    $formularioControllerFactory,
    $transporteControllerFactory,
    $visitaControllerFactory,
    $anexoControllerFactory,
    $historicoControllerFactory
);