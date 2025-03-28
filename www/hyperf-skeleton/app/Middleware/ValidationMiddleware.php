<?php

declare(strict_types=1);

namespace App\Middleware;

use Hyperf\Contract\ConfigInterface;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ValidationMiddleware implements MiddlewareInterface
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var HttpResponse
     */
    protected $response;

    /**
     * @var ValidatorFactoryInterface
     */
    protected $validationFactory;

    public function __construct(ContainerInterface $container, HttpResponse $response, RequestInterface $request, ValidatorFactoryInterface $validationFactory)
    {
        $this->container         = $container;
        $this->response          = $response;
        $this->request           = $request;
        $this->validationFactory = $validationFactory;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler->handle($request);
    }

    /**
     * 验证请求数据
     *
     * @param array $rules 验证规则
     * @param array $messages 错误信息
     * @return array 验证通过的数据
     */
    public function validate(array $rules, array $messages = []): array
    {
        $validator = $this->validationFactory->make(
            $this->request->all(),
            $rules,
            $messages
        );

        if ($validator->fails()) {
            $errorMessage = $validator->errors()->first();
            $this->response->json([
                'code'    => 422,
                'message' => $errorMessage,
                'data'    => null,
            ])->withStatus(422);
            exit;
        }

        return $validator->validated();
    }
}