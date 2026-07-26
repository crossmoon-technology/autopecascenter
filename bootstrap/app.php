<?php

use App\Http\Middleware\FilamentAuthenticate;
use App\Http\Middleware\RedirectExpiredSellerTrial;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Sem isso, $request->ip() (usado pro registration_ip único em RegisterRequest)
        // vê o IP de quem quer que esteja na frente do nginx (load balancer, CDN) em vez
        // do visitante de verdade — em produção, atrás de qualquer proxy reverso, isso
        // faria TODO mundo parecer vir do mesmo IP e travaria o cadastro de vendedor
        // depois do primeiro. '*' confia em qualquer proxy imediato; troque pelo CIDR
        // real do balanceador/CDN de produção se quiser restringir mais.
        $middleware->trustProxies(at: '*');

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(function () {
            return FilamentAuthenticate::panelUrlForRole(
                Auth::user()->role
            );
        });

        // Sem isso, o sorter de prioridade de middleware do Laravel (SortedMiddleware)
        // sempre empurra o FilamentAuthenticate pra antes de qualquer middleware "não
        // priorizado" — porque ele herda de Illuminate\Auth\Middleware\Authenticate, que
        // implementa Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests, e essa
        // INTERFACE já está no middlewarePriority padrão do Laravel. Isso acontecia mesmo
        // com o RedirectExpiredSellerTrial listado antes dele no ->authMiddleware() do
        // painel. O "before" aqui precisa ser essa interface (não a classe concreta do
        // Filament), porque addToMiddlewarePriorityBefore só reconhece algo que já esteja
        // litaralmente no middlewarePriority padrão.
        $middleware->prependToPriorityList(
            before: AuthenticatesRequests::class,
            prepend: RedirectExpiredSellerTrial::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
