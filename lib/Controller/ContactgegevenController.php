<?php

declare(strict_types=1);

namespace OCA\ContactgegevensRegister\Controller;

use OCA\ContactgegevensRegister\AppInfo\Application;
use OCA\ContactgegevensRegister\Exception\NietGevondenException;
use OCA\ContactgegevensRegister\ResponseDefinitions;
use OCA\ContactgegevensRegister\Service\ContactgegevenService;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * The endpoints are public until the token system is added (ADR 0001 requires access to be denied by default).
 *
 * @psalm-import-type ContactgegevensRegisterContactgegeven from ResponseDefinitions
 * @psalm-import-type ContactgegevensRegisterFout from ResponseDefinitions
 * @psalm-api Entry point: called through its routes
 */
#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
final class ContactgegevenController extends ApiController {
	public function __construct(
		IRequest $request,
		private readonly ContactgegevenService $service,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * List the contactgegevens of an identiteit
	 *
	 * @param string $uuid UUID of the identiteit
	 * @param bool $isGeverifieerd Only return verified contactgegevens
	 * @return JSONResponse<Http::STATUS_OK, list<ContactgegevensRegisterContactgegeven>, array{}>|JSONResponse<Http::STATUS_NOT_FOUND, ContactgegevensRegisterFout, array{}>
	 *
	 * 200: Contactgegevens returned, oldest first
	 * 404: Identiteit not found
	 */
	#[PublicPage]
	#[NoCSRFRequired]
	#[FrontpageRoute(verb: 'GET', url: '/api/v1/identiteiten/{uuid}/contactgegevens')]
	public function index(string $uuid, bool $isGeverifieerd = false): JSONResponse {
		try {
			return new JSONResponse($this->service->findByIdentiteit($uuid, $isGeverifieerd));
		} catch (NietGevondenException $e) {
			return new JSONResponse(['message' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		}
	}
}
