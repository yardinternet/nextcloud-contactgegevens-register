<?php

declare(strict_types=1);

namespace OCA\ContactgegevensRegister\Controller;

use OCA\ContactgegevensRegister\AppInfo\Application;
use OCA\ContactgegevensRegister\Exception\DubbelContactgegevenException;
use OCA\ContactgegevensRegister\Exception\NietGevondenException;
use OCA\ContactgegevensRegister\Exception\OngeldigeInvoerException;
use OCA\ContactgegevensRegister\ResponseDefinitions;
use OCA\ContactgegevensRegister\Service\ContactgegevenService;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
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

	/**
	 * Add a contactgegeven to an identiteit
	 *
	 * The new contactgegeven is not verified. It becomes primary when the identiteit has no other contactgegeven
	 * of that soort.
	 *
	 * @param string $uuid UUID of the identiteit
	 * @param string $soort Kind of contactgegeven, only `email` for now
	 * @param string $waarde The email address
	 * @return JSONResponse<Http::STATUS_CREATED, ContactgegevensRegisterContactgegeven, array{}>|JSONResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND|Http::STATUS_CONFLICT, ContactgegevensRegisterFout, array{}>
	 *
	 * 201: Contactgegeven added
	 * 400: Invalid soort or waarde
	 * 404: Identiteit not found
	 * 409: The identiteit already has this waarde, compared case-insensitively
	 */
	#[PublicPage]
	#[NoCSRFRequired]
	#[FrontpageRoute(verb: 'POST', url: '/api/v1/identiteiten/{uuid}/contactgegevens')]
	public function create(string $uuid, string $soort, string $waarde): JSONResponse {
		try {
			return new JSONResponse($this->service->add($uuid, $soort, $waarde), Http::STATUS_CREATED);
		} catch (OngeldigeInvoerException $e) {
			return new JSONResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		} catch (NietGevondenException $e) {
			return new JSONResponse(['message' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		} catch (DubbelContactgegevenException $e) {
			return new JSONResponse(['message' => $e->getMessage()], Http::STATUS_CONFLICT);
		}
	}

	/**
	 * Mark a contactgegeven as verified
	 *
	 * The register sets today's date (UTC). Repeating it keeps the original date; verification can't be undone.
	 *
	 * @param string $uuid UUID of the contactgegeven
	 * @return JSONResponse<Http::STATUS_OK, ContactgegevensRegisterContactgegeven, array{}>|JSONResponse<Http::STATUS_NOT_FOUND, ContactgegevensRegisterFout, array{}>
	 *
	 * 200: Contactgegeven verified
	 * 404: Contactgegeven not found
	 */
	#[PublicPage]
	#[NoCSRFRequired]
	#[FrontpageRoute(verb: 'POST', url: '/api/v1/contactgegevens/{uuid}/verifieer')]
	public function verify(string $uuid): JSONResponse {
		try {
			return new JSONResponse($this->service->verify($uuid));
		} catch (NietGevondenException $e) {
			return new JSONResponse(['message' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * Make a contactgegeven the primary one
	 *
	 * Clears the flag on the current primary contactgegeven of the same identiteit and soort. It doesn't have to be
	 * verified. Repeating it changes nothing.
	 *
	 * @param string $uuid UUID of the contactgegeven
	 * @return JSONResponse<Http::STATUS_OK, ContactgegevensRegisterContactgegeven, array{}>|JSONResponse<Http::STATUS_NOT_FOUND, ContactgegevensRegisterFout, array{}>
	 *
	 * 200: Contactgegeven is the primary one
	 * 404: Contactgegeven not found
	 */
	#[PublicPage]
	#[NoCSRFRequired]
	#[FrontpageRoute(verb: 'POST', url: '/api/v1/contactgegevens/{uuid}/primair')]
	public function makePrimair(string $uuid): JSONResponse {
		try {
			return new JSONResponse($this->service->makePrimair($uuid));
		} catch (NietGevondenException $e) {
			return new JSONResponse(['message' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		}
	}

	/**
	 * Forget a contactgegeven
	 *
	 * The contactgegeven is deleted. When it was the primary one, the identiteit has none until another one is made
	 * primary.
	 *
	 * @param string $uuid UUID of the contactgegeven
	 * @return Response<Http::STATUS_NO_CONTENT, array{}>|JSONResponse<Http::STATUS_NOT_FOUND, ContactgegevensRegisterFout, array{}>
	 *
	 * 204: Contactgegeven deleted
	 * 404: Contactgegeven not found
	 */
	#[PublicPage]
	#[NoCSRFRequired]
	#[FrontpageRoute(verb: 'DELETE', url: '/api/v1/contactgegevens/{uuid}')]
	public function destroy(string $uuid): Response {
		try {
			$this->service->delete($uuid);
		} catch (NietGevondenException $e) {
			return new JSONResponse(['message' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		}

		return new Response(Http::STATUS_NO_CONTENT);
	}
}
