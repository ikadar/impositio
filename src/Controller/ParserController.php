<?php

namespace App\Controller;

use App\Application\Joblang\ResponseTransformer\JoblangScriptParseResponseTransformer;
use App\Application\Joblang\UseCase\ParseJoblangScript\JoblangScriptParseRequestModel;
use App\Application\Joblang\UseCase\ParseJoblangScript\ParseJoblangScriptUseCase;
use App\Domain\Joblang\Interfaces\JoblangServiceInterface;
use App\Entity\JoblangScript;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;

class ParserController extends AbstractController
{

    public function __construct(
        private ParseJoblangScriptUseCase $useCase,
        protected JoblangScriptParseResponseTransformer $responseTransformer,
        protected JoblangServiceInterface $joblangService,
        protected KernelInterface $kernel,
    )
    {
    }

    #[Route(path: '/parse', requirements: [], methods: ['POST'])]
    public function parse(
        Request $request
    ): JsonResponse
    {
        $joblangScriptData = $this->processPayload($request);

        $requestModel = new JoblangScriptParseRequestModel($joblangScriptData);
        $responseModel = $this->useCase->execute($requestModel);
        $response = $this->responseTransformer->transform($responseModel);

        return new JsonResponse(
            ["scriptId" => $responseModel->scriptId],
            JsonResponse::HTTP_OK
        );
    }

    protected function processPayload($request): string
    {
        $data = $request->getContent();
        return trim($data);
    }

//    protected function saveData($joblangScriptData): JoblangScript
//    {
//        return $this->joblangService->parseAndPersistScript($joblangScriptData);
//    }

}