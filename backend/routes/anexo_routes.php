<?php

declare(strict_types=1);

use Elos\Controllers\AnexoController;
use Elos\Services\AuthorizationService;
use Elos\Services\HistoricoService;

const ANEXO_TAMANHO_MAXIMO = 10 * 1024 * 1024;

// Extensão aceita => tipos de conteúdo (detectados pelo finfo) que ela pode ter.
const ANEXO_ZIP_OFFICE = ['application/zip', 'application/octet-stream'];
const ANEXO_TIPOS_PERMITIDOS = [
    'pdf' => ['application/pdf'],
    'jpg' => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
    'png' => ['image/png'],
    'webp' => ['image/webp'],
    'gif' => ['image/gif'],
    'txt' => ['text/plain'],
    'csv' => ['text/plain', 'text/csv', 'application/csv'],
    'doc' => ['application/msword', 'application/CDFV2', 'application/x-ole-storage'],
    'xls' => ['application/vnd.ms-excel', 'application/CDFV2', 'application/x-ole-storage'],
    'ppt' => ['application/vnd.ms-powerpoint', 'application/CDFV2', 'application/x-ole-storage'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', ...ANEXO_ZIP_OFFICE],
    'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', ...ANEXO_ZIP_OFFICE],
    'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', ...ANEXO_ZIP_OFFICE],
    'odt' => ['application/vnd.oasis.opendocument.text', ...ANEXO_ZIP_OFFICE],
    'ods' => ['application/vnd.oasis.opendocument.spreadsheet', ...ANEXO_ZIP_OFFICE],
    'odp' => ['application/vnd.oasis.opendocument.presentation', ...ANEXO_ZIP_OFFICE],
];

/**
 * @param callable(): AnexoController $anexoControllerFactory
 * @param callable(): AuthorizationService $authorizationFactory
 */
function handleAnexoRequest(
    string $method,
    int $eventoId,
    callable $anexoControllerFactory,
    callable $authorizationFactory
): void {
    if ($method === 'GET') {
        requireRole($authorizationFactory, 'COLABORADOR');

        sendJsonResponse(200, [
            'anexos' => $anexoControllerFactory()->indexByEvento($eventoId),
        ]);
    }

    if ($method === 'POST') {
        requireRole($authorizationFactory, 'GESTOR');

        if (
            !isset($_FILES['arquivo'])
            || !is_array($_FILES['arquivo'])
        ) {
            sendJsonResponse(422, [
                'erro' => 'Arquivo não enviado.',
            ]);
        }

        $arquivo = $_FILES['arquivo'];

        if (
            !isset(
                $arquivo['name'],
                $arquivo['type'],
                $arquivo['tmp_name'],
                $arquivo['error'],
                $arquivo['size']
            )
        ) {
            sendJsonResponse(422, [
                'erro' => 'Dados do arquivo inválidos.',
            ]);
        }

        if ((int) $arquivo['error'] !== UPLOAD_ERR_OK) {
            sendJsonResponse(422, [
                'erro' => 'Não foi possível enviar o arquivo.',
            ]);
        }

        $nomeOriginal = $arquivo['name'];
        $tipo = $arquivo['type'];
        $tamanho = (int) $arquivo['size'];
        $tmpName = $arquivo['tmp_name'];

        if (
            !is_string($nomeOriginal)
            || trim($nomeOriginal) === ''
            || !is_string($tipo)
            || trim($tipo) === ''
            || !is_string($tmpName)
            || !is_file($tmpName)
            || $tamanho < 1
        ) {
            sendJsonResponse(422, [
                'erro' => 'Arquivo inválido.',
            ]);
        }

        if ($tamanho > ANEXO_TAMANHO_MAXIMO) {
            sendJsonResponse(413, [
                'erro' => 'O arquivo passa de 10 MB.',
            ]);
        }

        $extensao = strtolower(
            pathinfo($nomeOriginal, PATHINFO_EXTENSION)
        );

        // O tipo informado pelo navegador não é confiável: vale o que o
        // conteúdo do arquivo é, e ele precisa combinar com a extensão.
        $tipoReal = (new finfo(FILEINFO_MIME_TYPE))->file($tmpName);

        if (
            !isset(ANEXO_TIPOS_PERMITIDOS[$extensao])
            || !is_string($tipoReal)
            || !in_array($tipoReal, ANEXO_TIPOS_PERMITIDOS[$extensao], true)
        ) {
            sendJsonResponse(415, [
                'erro' => 'Tipo de arquivo não aceito. Envie PDF, imagem, '
                    . 'documento, planilha, apresentação ou texto.',
            ]);
        }

        $tipo = $tipoReal;
        $nome = bin2hex(random_bytes(16)) . '.' . $extensao;

        $diretorio = dirname(__DIR__) . '/storage/anexos';

        if (!is_dir($diretorio)) {
            if (!mkdir($diretorio, 0775, true) && !is_dir($diretorio)) {
                sendJsonResponse(500, [
                    'erro' => 'Não foi possível criar o diretório de anexos.',
                ]);
            }
        }

        $caminhoFisico = $diretorio . '/' . $nome;
        $caminho = 'storage/anexos/' . $nome;

        if (!move_uploaded_file($tmpName, $caminhoFisico)) {
            sendJsonResponse(500, [
                'erro' => 'Não foi possível salvar o arquivo.',
            ]);
        }

        $anexo = $anexoControllerFactory()->create(
            $eventoId,
            $nome,
            $nomeOriginal,
            $caminho,
            $tipo,
            $tamanho
        );

        if ($anexo === null) {
            if (is_file($caminhoFisico)) {
                unlink($caminhoFisico);
            }

            sendJsonResponse(500, [
                'erro' => 'Não foi possível registrar o anexo.',
            ]);
        }

        HistoricoService::registrar($eventoId, 'Anexo adicionado', $nomeOriginal);

        sendJsonResponse(201, [
            'mensagem' => 'Anexo criado com sucesso.',
            'anexo' => $anexo,
        ]);
    }

    sendJsonResponse(405, [
        'erro' => 'Método não permitido.',
    ]);
}

/**
 * @param callable(): AnexoController $anexoControllerFactory
 * @param callable(): AuthorizationService $authorizationFactory
 */
function handleAnexoByIdRequest(
    string $method,
    int $eventoId,
    int $id,
    callable $anexoControllerFactory,
    callable $authorizationFactory
): void {
    if ($method === 'GET') {
        requireRole($authorizationFactory, 'COLABORADOR');

        $anexo = $anexoControllerFactory()->show(
            $id,
            $eventoId
        );

        if ($anexo === null) {
            sendJsonResponse(404, [
                'erro' => 'Anexo não encontrado.',
            ]);
        }

        sendJsonResponse(200, [
            'anexo' => $anexo,
        ]);
    }

    if ($method === 'DELETE') {
        requireRole($authorizationFactory, 'GESTOR');

        $anexo = $anexoControllerFactory()->show(
            $id,
            $eventoId
        );

        if ($anexo === null) {
            sendJsonResponse(404, [
                'erro' => 'Anexo não encontrado.',
            ]);
        }

        $deleted = $anexoControllerFactory()->delete(
            $id,
            $eventoId
        );

        if (!$deleted) {
            sendJsonResponse(404, [
                'erro' => 'Anexo não encontrado.',
            ]);
        }

        $caminhoFisico = dirname(__DIR__) . '/' . $anexo['caminho'];

        if (is_file($caminhoFisico)) {
            unlink($caminhoFisico);
        }

        HistoricoService::registrar($eventoId, 'Anexo removido', (string) ($anexo['nome_original'] ?? ''));

        sendJsonResponse(200, [
            'mensagem' => 'Anexo excluído com sucesso.',
        ]);
    }

    sendJsonResponse(405, [
        'erro' => 'Método não permitido.',
    ]);
}
