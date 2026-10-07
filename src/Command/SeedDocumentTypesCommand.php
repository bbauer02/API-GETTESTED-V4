<?php

namespace App\Command;

use App\Entity\DocumentTemplate;
use App\Entity\DocumentType;
use App\Repository\DocumentTemplateRepository;
use App\Service\DocumentAccessService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Crée (ou met à jour) les 5 types de documents candidats et leurs templates master :
 * REGISTRATION_CONFIRMATION, REGISTRATION_CERTIFICATE, CONVOCATION, ATTENDANCE_CERTIFICATE, PAYMENT_CERTIFICATE.
 * Délègue CONVOCATION et ATTENDANCE_CERTIFICATE aux commandes de seed existantes.
 */
#[AsCommand(
    name: 'app:seed-document-types',
    description: 'Create the 5 standard document types and their master templates',
)]
class SeedDocumentTypesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly DocumentTemplateRepository $templateRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // 1. Types de documents
        foreach (DocumentAccessService::TYPES as $code => $label) {
            $documentType = $this->em->getRepository(DocumentType::class)->findOneBy(['code' => $code]);
            if (!$documentType) {
                $documentType = new DocumentType();
                $documentType->setCode($code);
                $documentType->setLabel($label);
                $this->em->persist($documentType);
                $io->note(sprintf('DocumentType %s created.', $code));
            }
        }
        $this->em->flush();

        // 2. Templates master simples (les templates convocation / attestation de présence ont leur commande dédiée)
        $this->upsertMasterTemplate(
            DocumentAccessService::REGISTRATION_CONFIRMATION,
            "Confirmation d'inscription — Modèle standard",
            $this->registrationConfirmationHtml(),
            $io
        );
        $this->upsertMasterTemplate(
            DocumentAccessService::REGISTRATION_CERTIFICATE,
            "Attestation d'inscription — Modèle standard",
            $this->registrationCertificateHtml(),
            $io
        );
        $this->upsertMasterTemplate(
            DocumentAccessService::PAYMENT_CERTIFICATE,
            'Attestation de paiement — Modèle standard',
            $this->paymentCertificateHtml(),
            $io
        );

        // 3. Convocation + attestation de présence via les commandes existantes
        $application = $this->getApplication();
        if ($application) {
            foreach (['app:seed-convocation-template', 'app:seed-attestation-presence-template'] as $name) {
                $application->find($name)->run(new ArrayInput([]), $output);
            }
        }

        $io->success('Document types and master templates seeded.');

        return Command::SUCCESS;
    }

    private function upsertMasterTemplate(string $code, string $label, string $content, SymfonyStyle $io): void
    {
        $documentType = $this->em->getRepository(DocumentType::class)->findOneBy(['code' => $code]);
        $template = $this->templateRepository->findOneBy(['documentType' => $documentType, 'institute' => null]);

        if ($template) {
            $template->setLabel($label);
            $template->setContent($content);
            $io->text(sprintf('Master template %s updated.', $code));
        } else {
            $template = new DocumentTemplate();
            $template->setLabel($label);
            $template->setDocumentType($documentType);
            $template->setContent($content);
            $template->setIsDefault(false);
            $this->em->persist($template);
            $io->text(sprintf('Master template %s created.', $code));
        }

        $this->em->flush();
    }

    private function mergeField(string $field, string $label): string
    {
        return '<span data-merge-field="' . $field . '" data-merge-label="' . $label . '" class="merge-field" contenteditable="false">{{ ' . $label . ' }}</span>';
    }

    private function header(): string
    {
        $m = fn (string $f, string $l) => $this->mergeField($f, $l);
        $inst = $m('institute.label', "Nom de l'institut");
        $instAddr = $m('institute.address', 'Adresse institut');
        $instZip = $m('institute.zipcode', 'CP institut');
        $instCity = $m('institute.city', 'Ville institut');
        $instEmail = $m('institute.email', 'Email institut');
        $instLogo = $m('institute.logo', 'Logo institut');
        $candCiv = $m('candidate.civility', 'Civilite');
        $candFirst = $m('candidate.firstname', 'Prenom');
        $candLast = $m('candidate.lastname', 'Nom');
        $candAddr = $m('candidate.address', 'Adresse');
        $candZip = $m('candidate.zipcode', 'Code postal');
        $candCity = $m('candidate.city', 'Ville');

        return <<<HTML
<table style="width:100%;margin-bottom:24px;"><tr>
<td style="vertical-align:top;width:50%;">
<p style="margin:0 0 8px 0;">$instLogo</p>
<p><strong style="font-size:16px;">$inst</strong></p>
<p style="font-size:12px;color:#555;margin:2px 0;">$instAddr</p>
<p style="font-size:12px;color:#555;margin:2px 0;">$instZip $instCity</p>
<p style="font-size:12px;color:#555;margin:2px 0;">$instEmail</p>
</td>
<td style="vertical-align:top;width:50%;text-align:right;">
<p>$candCiv $candFirst $candLast</p>
<p style="font-size:13px;color:#555;margin:2px 0;">$candAddr</p>
<p style="font-size:13px;color:#555;margin:2px 0;">$candZip $candCity</p>
</td>
</tr></table>
HTML;
    }

    private function title(string $title, string $subtitle): string
    {
        return <<<HTML
<table style="width:100%;margin-bottom:24px;"><tr><td style="text-align:center;padding:14px 0 10px 0;border:2px solid #1a1a2e;">
<p style="font-size:22px;font-weight:bold;color:#1a1a2e;margin:0;letter-spacing:2px;">$title</p>
<p style="font-size:14px;margin:4px 0 0 0;">$subtitle</p>
</td></tr></table>
HTML;
    }

    private function registrationConfirmationHtml(): string
    {
        $m = fn (string $f, string $l) => $this->mergeField($f, $l);
        $assessment = $m('session.assessment', 'Nom du test');
        $level = $m('session.level', 'Niveau');
        $sessionDate = $m('session.start', 'Date de la session');
        $ref = $m('enrollment.referenceNumber', 'Numero de dossier');
        $examsTable = $m('enrollment.examsTable', 'Tableau des epreuves');
        $candCiv = $m('candidate.civility', 'Civilite');
        $candLast = $m('candidate.lastname', 'Nom');
        $inst = $m('institute.label', "Nom de l'institut");

        return $this->header()
            . $this->title("CONFIRMATION D'INSCRIPTION", "$assessment — $level")
            . <<<HTML
<p>$candCiv $candLast,</p>
<p>Nous vous confirmons la bonne réception de votre inscription (dossier n° <strong>$ref</strong>) à la session du <strong>$sessionDate</strong>.</p>
<p>Épreuves auxquelles vous êtes inscrit(e) :</p>
<p>$examsTable</p>
<p>Votre convocation vous sera communiquée dès validation de la session par l'institut.</p>
<p></p>
<p><strong>$inst</strong></p>
HTML;
    }

    private function registrationCertificateHtml(): string
    {
        $m = fn (string $f, string $l) => $this->mergeField($f, $l);
        $assessment = $m('session.assessment', 'Nom du test');
        $level = $m('session.level', 'Niveau');
        $sessionDate = $m('session.start', 'Date de la session');
        $ref = $m('enrollment.referenceNumber', 'Numero de dossier');
        $candCiv = $m('candidate.civility', 'Civilite');
        $candFirst = $m('candidate.firstname', 'Prenom');
        $candLast = $m('candidate.lastname', 'Nom');
        $inst = $m('institute.label', "Nom de l'institut");
        $today = $m('document.date', 'Date du document');

        return $this->header()
            . $this->title("ATTESTATION D'INSCRIPTION", "$assessment — $level")
            . <<<HTML
<p>Je soussigné(e), représentant(e) de <strong>$inst</strong>, atteste que $candCiv <strong>$candFirst $candLast</strong> est inscrit(e) à la session d'examen <strong>$assessment</strong> ($level) du <strong>$sessionDate</strong>, sous le numéro de dossier <strong>$ref</strong>.</p>
<p>La présente attestation est délivrée pour servir et valoir ce que de droit.</p>
<p>Fait le $today.</p>
<p></p>
<p><strong>$inst</strong></p>
HTML;
    }

    private function paymentCertificateHtml(): string
    {
        $m = fn (string $f, string $l) => $this->mergeField($f, $l);
        $assessment = $m('session.assessment', 'Nom du test');
        $level = $m('session.level', 'Niveau');
        $sessionDate = $m('session.start', 'Date de la session');
        $ref = $m('enrollment.referenceNumber', 'Numero de dossier');
        $candCiv = $m('candidate.civility', 'Civilite');
        $candFirst = $m('candidate.firstname', 'Prenom');
        $candLast = $m('candidate.lastname', 'Nom');
        $inst = $m('institute.label', "Nom de l'institut");
        $invoiceNumber = $m('invoice.number', 'Numero de facture');
        $amount = $m('invoice.totalTTC', 'Montant TTC');
        $today = $m('document.date', 'Date du document');

        return $this->header()
            . $this->title('ATTESTATION DE PAIEMENT', "$assessment — $level")
            . <<<HTML
<p>Je soussigné(e), représentant(e) de <strong>$inst</strong>, atteste que $candCiv <strong>$candFirst $candLast</strong> s'est acquitté(e) du règlement de la facture n° <strong>$invoiceNumber</strong> d'un montant de <strong>$amount</strong> correspondant à son inscription (dossier n° $ref) à la session <strong>$assessment</strong> ($level) du <strong>$sessionDate</strong>.</p>
<p>La présente attestation est délivrée pour servir et valoir ce que de droit.</p>
<p>Fait le $today.</p>
<p></p>
<p><strong>$inst</strong></p>
HTML;
    }
}
