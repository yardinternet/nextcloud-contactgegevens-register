<?php

declare(strict_types=1);

namespace OCA\ContactgegevensRegister\Controller;

use OCA\ContactgegevensRegister\AppInfo\Application;
use OCA\ContactgegevensRegister\Exception\OngeldigeInvoerException;
use OCA\ContactgegevensRegister\ResponseDefinitions;
use OCA\ContactgegevensRegister\Service\IdentiteitService;
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
 * @psalm-import-type ContactgegevensRegisterIdentiteit from ResponseDefinitions
 * @psalm-import-type ContactgegevensRegisterFout from ResponseDefinitions
 * @psalm-api Entry point: called through its routes
 */
#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
final class IdentiteitController extends ApiController {
	public function __construct(
		IRequest $request,
		private readonly IdentiteitService $service,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * Find or create the identiteit of an HMAC
	 *
	 * During a key rotation the client also sends the HMAC of the previous key. An identiteit that only has
	 * that HMAC is re-keyed and keeps its UUID.
	 *
	 * @param string $hmac HMAC-SHA256 of the login identifier, 64 lowercase hexadecimal characters
	 * @param string|null $vorigeHmac HMAC of the login identifier with the previous key, only during a key rotation
	 * @return JSONResponse<Http::STATUS_OK, ContactgegevensRegisterIdentiteit, array{}>|JSONResponse<Http::STATUS_CREATED, ContactgegevensRegisterIdentiteit, array{}>|JSONResponse<Http::STATUS_BAD_REQUEST, ContactgegevensRegisterFout, array{}>
	 *
	 * 200: Existing identiteit found or re-keyed
	 * 201: Identiteit created
	 * 400: Invalid HMAC, or vorigeHmac equals hmac
	 */
	#[PublicPage]
	#[NoCSRFRequired]
	#[FrontpageRoute(verb: 'POST', url: '/api/v1/identiteiten')]
	public function create(string $hmac, ?string $vorigeHmac = null): JSONResponse {
		try {
			$result = $this->service->findOrCreate($hmac, $vorigeHmac);
		} catch (OngeldigeInvoerException $e) {
			return new JSONResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}

		return new JSONResponse(
			$result['identiteit']->jsonSerialize(),
			$result['aangemaakt'] ? Http::STATUS_CREATED : Http::STATUS_OK,
		);
	}
}
