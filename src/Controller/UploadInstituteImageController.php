<?php

namespace App\Controller;

use App\Entity\Institute;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

class UploadInstituteImageController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/institutes/{id}/logo', name: 'api_upload_institute_logo', methods: ['POST'])]
    #[IsGranted('INSTITUTE_EDIT', subject: 'institute')]
    public function uploadLogo(Request $request, Institute $institute): JsonResponse
    {
        return $this->handleUpload($request, $institute, 'logo');
    }

    #[Route('/api/institutes/{id}/cover', name: 'api_upload_institute_cover', methods: ['POST'])]
    #[IsGranted('INSTITUTE_EDIT', subject: 'institute')]
    public function uploadCover(Request $request, Institute $institute): JsonResponse
    {
        return $this->handleUpload($request, $institute, 'cover');
    }

    private function handleUpload(Request $request, Institute $institute, string $type): JsonResponse
    {
        $file = $request->files->get('file');

        if (!$file) {
            return $this->hydraError(Response::HTTP_BAD_REQUEST, 'No file uploaded.');
        }

        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file->getMimeType(), $allowedTypes, true)) {
            return $this->hydraError(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'Invalid file type. Allowed: jpeg, png, gif, webp.'
            );
        }

        $maxSize = $type === 'cover' ? 10 * 1024 * 1024 : 5 * 1024 * 1024;
        if ($file->getSize() > $maxSize) {
            $maxMb = $maxSize / (1024 * 1024);
            return $this->hydraError(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                "File too large. Maximum size: {$maxMb} MB."
            );
        }

        // Delete old file
        $oldPath = $type === 'logo' ? $institute->getLogo() : $institute->getCoverImage();
        if ($oldPath) {
            $fullOldPath = $this->getParameter('kernel.project_dir') . '/public' . $oldPath;
            if (file_exists($fullOldPath)) {
                unlink($fullOldPath);
            }
        }

        // Generate unique filename and move
        $subDir = $type === 'logo' ? 'institutes/logos' : 'institutes/covers';
        $filename = Uuid::v4() . '.' . $file->guessExtension();
        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/' . $subDir;

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $file->move($uploadDir, $filename);

        $imagePath = '/uploads/' . $subDir . '/' . $filename;

        if ($type === 'logo') {
            $institute->setLogo($imagePath);
        } else {
            $institute->setCoverImage($imagePath);
        }

        $this->entityManager->flush();

        return new JsonResponse([
            '@context' => '/api/contexts/Institute',
            '@type' => 'Institute',
            $type === 'logo' ? 'logo' : 'coverImage' => $imagePath,
        ], Response::HTTP_OK, ['Content-Type' => 'application/ld+json']);
    }

    private function hydraError(int $status, string $detail): JsonResponse
    {
        return new JsonResponse([
            '@context' => '/api/contexts/Error',
            '@type' => 'Error',
            'title' => 'An error occurred',
            'detail' => $detail,
            'status' => $status,
        ], $status, ['Content-Type' => 'application/ld+json']);
    }
}
