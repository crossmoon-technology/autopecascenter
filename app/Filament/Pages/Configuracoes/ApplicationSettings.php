<?php

namespace App\Filament\Pages\Configuracoes;

use App\Filament\Concerns\HasHelpAction;
use App\Models\Setting;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class ApplicationSettings extends Page implements HasForms
{
    use HasHelpAction;
    use InteractsWithForms;

    protected string $view = 'filament.pages.configuracoes.application-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static string|UnitEnum|null $navigationGroup = 'Configurações';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Application';

    protected static ?string $title = 'Application';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    /**
     * Chaves de texto de contato (key/value) — os defaults aqui são os mesmos textos que
     * já estavam fixos no footer/contato antes dessa página existir, então nada muda
     * visualmente no site até alguém preencher isso de verdade.
     *
     * @return array<string, string>
     */
    public static function contactDefaults(): array
    {
        return [
            'contact_phone' => '(11) 99999-9999',
            'contact_email' => 'contato@autopecascenter.com.br',
            'contact_address' => 'Av. das Peças, 123 - São Paulo/SP, CEP: 01234-567',
            'business_hours' => 'Segunda a sexta, 8h às 18h',
        ];
    }

    /**
     * Planos exibidos na página pública de planos (resources/views/planos.blade.php), na
     * mesma ordem dos cards: avaliação gratuita, Básico, Profissional — os únicos três
     * planos que existem de verdade (ver PlanChoiceRequest, que só aceita esses valores).
     *
     * @return array<string, string>
     */
    public static function planSlugs(): array
    {
        return [
            'trial' => 'Avaliação gratuita',
            'basico' => 'Básico',
            'profissional' => 'Profissional',
        ];
    }

    /**
     * Título, descrição, preço (valor + período) e lista de features (uma por linha) de
     * cada plano — os defaults aqui são os mesmos textos que já estavam fixos no
     * planos.blade.php antes dessa página existir, então nada muda visualmente no site até
     * alguém preencher isso de verdade. A avaliação gratuita não tem preço numérico, por
     * isso "Grátis" / "por 7 dias" em vez de um valor em R$.
     *
     * @return array<string, string>
     */
    public static function planDefaults(): array
    {
        return [
            'plan_trial_name' => 'Avaliação gratuita',
            'plan_trial_description' => '7 dias com acesso completo ao plano Profissional, sem compromisso.',
            'plan_trial_price' => 'Grátis',
            'plan_trial_price_period' => 'por 7 dias',
            'plan_trial_features' => "Acesso completo ao plano Profissional\nSem necessidade de cartão de crédito\nCancele quando quiser",

            'plan_basico_name' => 'Básico',
            'plan_basico_description' => 'Para quem está começando a cotar online.',
            'plan_basico_price' => 'R$ 99',
            'plan_basico_price_period' => '/mês',
            'plan_basico_features' => "Até 50 cotações por mês\nAcesso a fornecedores integrados\nComparação de preços\nSuporte por e-mail",

            'plan_profissional_name' => 'Profissional',
            'plan_profissional_description' => 'Para equipes que vendem todos os dias.',
            'plan_profissional_price' => 'R$ 249',
            'plan_profissional_price_period' => '/mês',
            'plan_profissional_features' => "Cotações ilimitadas\nTodos os fornecedores integrados\nComparação e histórico de preços\nRelatórios e métricas de vendas\nSuporte prioritário",
        ];
    }

    /**
     * As chaves de rede social não têm um default de texto pra preencher no formulário —
     * ao contrário do contato, aqui não existe "valor padrão razoável", só vazio (pendente
     * de cadastro) ou uma URL real. Preenchê-las com "#" quebraria a validação de URL do
     * campo assim que o formulário fosse salvo sem editar todas elas. O "#" que aparece no
     * site público é só o fallback do footer/contato, aplicado na hora de exibir.
     *
     * @return list<string>
     */
    public static function socialKeys(): array
    {
        return ['social_facebook', 'social_instagram', 'social_linkedin', 'social_youtube'];
    }

    public function mount(): void
    {
        $data = collect(static::contactDefaults())
            ->merge(static::planDefaults())
            ->map(fn (string $default, string $key) => Setting::get($key, $default))
            ->all();

        foreach (static::socialKeys() as $key) {
            $data[$key] = Setting::get($key);
        }

        $this->form->fill($data);
    }

    protected function getHeaderActions(): array
    {
        return [$this->helpAction()];
    }

    protected function helpTitle(): string
    {
        return 'Como funciona a página Application';
    }

    protected function helpDescription(): string
    {
        return '<p>Esses dados aparecem no rodapé e na página de contato do site público (telefone, e-mail, endereço, horário de atendimento e redes sociais), além dos cards da página pública de planos (título, descrição, preço e lista de recursos). Alterar aqui atualiza o site inteiro.</p>';
    }

    /**
     * Monta a seção de formulário de um plano (título, descrição, preço + período e
     * features, uma por linha). Os três planos têm exatamente os mesmos campos, então o
     * card inteiro é montado aqui em vez de repetir os componentes três vezes em form().
     */
    private function planSection(string $slug, string $label): Section
    {
        return Section::make("Plano: {$label}")
            ->columns(2)
            ->components([
                TextInput::make("plan_{$slug}_name")
                    ->label('Título'),
                TextInput::make("plan_{$slug}_description")
                    ->label('Descrição')
                    ->columnSpanFull(),
                TextInput::make("plan_{$slug}_price")
                    ->label('Preço'),
                TextInput::make("plan_{$slug}_price_period")
                    ->label('Período')
                    ->helperText('Ex.: "/mês" ou "por 7 dias".'),
                Textarea::make("plan_{$slug}_features")
                    ->label('Recursos')
                    ->helperText('Uma por linha.')
                    ->rows(4)
                    ->columnSpanFull(),
            ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                ...collect(static::planSlugs())
                    ->map(fn (string $label, string $slug) => $this->planSection($slug, $label))
                    ->values()
                    ->all(),
                Section::make('Contato')
                    ->columns(2)
                    ->components([
                        TextInput::make('contact_phone')
                            ->label('Telefone')
                            ->tel(),
                        TextInput::make('contact_email')
                            ->label('E-mail')
                            ->email(),
                        TextInput::make('contact_address')
                            ->label('Endereço')
                            ->columnSpanFull(),
                        TextInput::make('business_hours')
                            ->label('Horário de atendimento')
                            ->columnSpanFull(),
                    ]),
                Section::make('Redes sociais')
                    ->columns(2)
                    ->components([
                        TextInput::make('social_facebook')
                            ->label('Facebook')
                            ->url(),
                        TextInput::make('social_instagram')
                            ->label('Instagram')
                            ->url(),
                        TextInput::make('social_linkedin')
                            ->label('LinkedIn')
                            ->url(),
                        TextInput::make('social_youtube')
                            ->label('YouTube')
                            ->url(),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        foreach ($this->form->getState() as $key => $value) {
            Setting::set($key, $value);
        }

        Notification::make()
            ->title('Configurações salvas.')
            ->success()
            ->send();
    }
}
