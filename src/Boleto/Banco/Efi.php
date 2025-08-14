<?php
namespace Eduardokum\LaravelBoleto\Boleto\Banco;

use Eduardokum\LaravelBoleto\Boleto\AbstractBoleto;
use Eduardokum\LaravelBoleto\Contracts\Boleto\Boleto as BoletoContract;
use Eduardokum\LaravelBoleto\Util;

class Efi extends AbstractBoleto implements BoletoContract
{
    /** @var string */
    protected $codigoBanco = self::COD_BANCO_EFI;

    /** @var string[] */
    protected $carteiras = ['01'];

    /** @var array<string,string> */
    protected $especiesCodigo = ['DM' => '02']; // Duplicata Mercantil

    /** @var string */
    protected $convenio = '';

    public function __construct(array $params = [])
    {
        parent::__construct($params);
        $this->addCampoObrigatorio('convenio');
    }

    /** Convenio (use string para preservar zeros à esquerda). */
    public function setConvenio(string $convenio): self
    {
        $this->convenio = $convenio;
        return $this;
    }

    public function getConvenio(): string
    {
        return $this->convenio;
    }

    /** Nosso Número conforme já definido externamente. */
    protected function gerarNossoNumero(): string
    {
        return (string) $this->numero;
    }

    public function getNossoNumeroBoleto(): string
    {
        return $this->getNossoNumero();
    }

    /**
     * Campo livre Efí (25): DV(NN, 5) + NN(20)
     */
    protected function getCampoLivre(): string
    {
        if ($this->campoLivre) {
            return $this->campoLivre;
        }

        $nn   = Util::numberFormatGeral($this->getNossoNumero(), 20);
        $dvNN = Util::numberFormatGeral($this->modulo11($nn, 2, 9, 0), 5);

        return $this->campoLivre = $dvNN . $nn;
    }

    /**
     * Método onde qualquer boleto deve extender para gerar o código da posição de 20 a 44
     *
     * @param $campoLivre
     *
     * @return array
     */
    static public function parseCampoLivre($campoLivre) {
        return [
            'codigoCliente' => null,
            'agenciaDv' => null,
            'contaCorrente' => null,
            'contaCorrenteDv' => null,
            'carteira' => substr($campoLivre, 0, 1),
            'agencia' => substr($campoLivre, 1, 4),
            'modalidade' => substr($campoLivre, 5, 2),
            'convenio' => substr($campoLivre, 7, 7),
            'nossoNumero' => substr($campoLivre, 14, 7),
            'nossoNumeroDv' => substr($campoLivre, 21, 1),
            'nossoNumeroFull' => substr($campoLivre, 14, 8),
            'parcela' => substr($campoLivre, 22, 3),
        ];
    }

    /**
     * Agência/Código do Beneficiário: "AGENCIA/ CONVENIO(9)"
     */
    public function getAgenciaCodigoBeneficiario(): string
    {
        return $this->getAgencia() . '/ ' . Util::numberFormatGeral($this->getConvenio(), 9);
    }

    /**
     * Código de barras:
     * BBB M FFFF VVVVVVVVVV [CampoLivre(25)]
     * DV geral: Mód 11, pesos 2..9. Se 0, 10, 11 => 1.
     */
    public function getCodigoBarras(): string
    {
        if (!empty($this->campoCodigoBarras)) {
            return $this->campoCodigoBarras;
        }

        if (!$this->isValid($messages)) {
            throw new \Exception('Campos requeridos pelo banco ausentes: ' . $messages);
        }

        $preCB      = Util::numberFormatGeral($this->getCodigoBanco(), 3) . $this->getMoeda();
        $fator      = '0000'; // Efí exige 0000
        $valor      = Util::numberFormatGeral($this->getValor(), 10);
        $campoLivre = $this->getCampoLivre();

        $semDV = $preCB . $fator . $valor . $campoLivre;

        $resto = $this->modulo11($semDV);
        $dv    = in_array($resto, [0, 10, 11], true) ? 1 : $resto;

        return $this->campoCodigoBarras = substr($semDV, 0, 4) . $dv . substr($semDV, 4);
    }

    /**
     * Módulo 11 genérico (pesos 2..$base).
     * Quando $x10 === 0, aplica regra de DV (10->$resto10; 0|>9->1).
     */
    public function modulo11(string $n, int $factor = 2, int $base = 9, int $x10 = 0, int $resto10 = 0): int
    {
        $sum = 0; $f = $factor;
        for ($i = strlen($n) - 1; $i >= 0; $i--) {
            $sum += ((int) $n[$i]) * $f;
            $f = ($f >= $base) ? 2 : $f + 1;
        }

        if ($x10 === 0) {
            $dig = ($sum * 10) % 11;
            if ($dig === 10) $dig = $resto10;
            if ($dig === 0 || $dig > 9) $dig = 1;
            return $dig;
        }

        return $sum % 11;
    }
}
