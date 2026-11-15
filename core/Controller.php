<?php

declare(strict_types=1);

namespace Agus;

use Agus\Exceptions\HttpException;
use Agus\Exceptions\ValidationException;
use Agus\Http\Request;
use Agus\Http\Response;
use Agus\Support\Validator;
use Agus\View\View;

/** Base class for app/Controllers. Gives short helpers for the common replies. */
abstract class Controller
{
    protected function view(string $view, array $data = [], int $status = 200): Response
    {
        return Response::html(View::render($view, $data), $status);
    }

    protected function json(mixed $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    protected function redirect(string $to): Response
    {
        return Response::redirect($to);
    }

    protected function back(): Response
    {
        return Response::back();
    }

    /**
     * Validates the request. On failure it stops the action and sends the user
     * back to the form with errors (or a 422 JSON reply for API calls).
     * On success it returns only the validated fields.
     */
    protected function validate(Request $request, array $rules, array $messages = []): array
    {
        $validator = Validator::make($request->all(), $rules, $messages);
        if ($validator->fails()) {
            throw new ValidationException($validator->errors(), $request->all());
        }
        return $validator->validated();
    }

    protected function abort(int $status, string $message = ''): never
    {
        throw new HttpException($status, $message);
    }
}
