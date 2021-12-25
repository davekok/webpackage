<?php

declare(strict_types=1);

namespace davekok\webpackage;

use davekok\http\HttpMessage;
use davekok\http\HttpRequest;
use davekok\http\HttpRequestHandler;
use davekok\http\HttpResponse;

class WebPackageHttpRequestHandler implements HttpRequestHandler
{
    public function __construct(
        private readonly Store $store,
    ) {}

    private function handleHttpRequest(HttpRequest $requestHttp, ResponseFactory $factory): HttpResponse
    {
        return match ($request->url->path) {
            "/"     => $this->handleMainRequest($request, $factory),
            default => $this->handleFileRequest($request, $factory),
        };
    }

    private function handleMainRequest(HttpRequest $request, ResponseFactory $factory): HttpResponse
    {
        return match ($request->method) {
            HttpMessage::GET     => $this->handleGetMainRequest(request: $request, $factory, head: false),
            HttpMessage::OPTIONS => $this->handleOptionsMainRequest(request: $request, $factory),
            HttpMessage::HEAD    => $this->handleGetMainRequest(request: $request, $factory, head: true),
            HttpMessage::PUT     => $this->handlePutMainRequest(request: $request, $factory),
            default              => $this->methodNotAllowed(
                method:  $request->method,
                allowed: [
                    HttpMessage::OPTIONS,
                    HttpMessage::HEAD,
                    HttpMessage::GET,
                    HttpMessage::PUT,
                ]
            ),
        };
    }

    private function handleOptionsMainRequest(HttpRequest $request, ResponseFactory $factory): self
    {
        return $factory->createEmptyRequest();
        return $this->createHttpResponse(new HttpResponse(
            status:  HttpStatus::NO_CONTENT,
            headers: [
                HttpMessage::ALLOW => implode(", ", [
                    HttpMessage::OPTIONS,
                    HttpMessage::HEAD,
                    HttpMessage::GET,
                    HttpMessage::PUT,
                ])
            ],
        ));
    }

    private function handleGetMainRequest(HttpRequest $request, bool $head = false): self
    {
        if (isset($this->webPackage) && isset($this->webPackage->files[$request->url->path])) {
            $file = $this->webPackage->files[$request->url->path];
            return match ($request->accept([$file->contentType, WebPackage::CONTENT_TYPE])) {
                $file->contentType       => $this->handleFileGetRequest(file: $file, head: $head),
                WebPackage::CONTENT_TYPE => $this->handleGetWebPackageRequest(head: $head),
                default                  => $this->notAcceptable(),
            };
        }
        if (isset($this->webPackage)) {
            return match ($request->accept([WebPackage::CONTENT_TYPE])) {
                WebPackage::CONTENT_TYPE => $this->handleGetWebPackageRequest(head: $head),
                default                  => $this->notAcceptable(),
            };
        }
        return $this->notFound();
    }

    private function handlePutMainRequest(HttpRequest $request): self
    {
        $contentType = $request->contentType();
        return match ($contentType) {
            WebPackage::CONTENT_TYPE => $this->handlePutWebPackageRequest(),
            default                  => $this->unsupportedMediaType($contentType, [WebPackage::CONTENT_TYPE]),
        };
    }

    private function handleGetWebPackageRequest(bool $head = false): HttpResponse
    {
        $headers = [
            HttpMessage::CONTENT_TYPE   => WebPackage::CONTENT_TYPE,
            HttpMessage::CONTENT_LENGTH => $this->webPackage->length,
            HttpMessage::ETAG           => $this->webPackage->signature,
            HttpMessage::LAST_MODIFIED  => $this->lastModified(),
        ];
        if ($head === true) {
            return new HttpResponse(status: HttpStatus::NO_CONTENT, headers: $headers);
        }
        return new HttpResponse(
            status: HttpStatus::OK,
            headers: $headers,
            content: $this->factory->createWrite($this->store->webPackage),
        );
    }

    private function handlePutWebPackageRequest(): self
    {
        return $this->readWebPackage(andThen: $this->handleWebPackage(...));
    }

    private function handleWebPackage(WebPackage|Throwable|null $value): self
    {
        return match (true) {
            $value instanceof WebPackage => $this->saveWebPackage($value),
            $value === null              => $this->close(),
            $value instanceof Throwable  => $this->badRequest($value),
        };
    }

    private function saveWebPackage(WebPackage $value): self
    {
        $this->store->webPackage = $value;
        $this->store->saveWebPackage();

        return $this->writeHttpResponse(new HttpResponse(status: HttpStatus::NO_CONTENT));
    }

    private function handleFileRequest(HttpRequest $request): HttpResponse
    {
        if (isset($this->store->webPackage->files[$request->url->path]) === false) {
            return $this->notFound();
        }
        $file = $this->store->webPackage->files[$request->url->path];
        return match ($request->method) {
            HttpMessage::GET  => $this->handleGetFileRequest(file: $file, head: false),
            HttpMessage::HEAD => $this->handleGetFileRequest(file: $file, head: true),
            default           => $this->methodNotAllowed(
                method: $request->method,
                allowed: [HttpMessage::HEAD, HttpMessage::GET]
            ),
        };
    }

    private function handleFileGetRequest(File $file, bool $head = false): HttpResponse
    {
        $headers = [
            HttpMessage::CONTENT_TYPE   => $file->contentType,
            HttpMessage::CONTENT_LENGTH => $file->contentLength,
            HttpMessage::ETAG           => $file->contentHash,
            HttpMessage::LAST_MODIFIED  => $this->lastModified(),
        ];
        if ($head === true) {
            return new HttpResponse(status: HttpStatus::NO_CONTENT, headers: $headers);
        }
        return new HttpResponse(status: HttpStatus::OK, headers: $headers, body: $file->content);
    }

    private function lastModified(): string
    {
        return str_replace("+0000", "GMT", $this->webPackage->buildDate->format("r"));
    }
}
