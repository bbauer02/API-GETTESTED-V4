<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

class UploadAvatarController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/api/users/me/avatar', name: 'api_upload_my_avatar', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function uploadMyAvatar(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->handleUpload($request, $user);
    }

    #[Route('/api/users/{id}/avatar', name: 'api_upload_user_avatar', methods: ['POST'])]
    #[IsGranted('ROLE_PLATFORM_ADMIN')]
    public function uploadUserAvatar(Request $request, User $user): JsonResponse
    {
        return $this->handleUpload($request, $user);
    }

    private function handleUpload(Request $request, User $user): JsonResponse
    {
        $file = $request->files->get('file');

        if (!$file) {
            return $this->hydraError(Response::HTTP_BAD_REQUEST, 'No file uploaded.');
        }

        // Validate MIME type
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file->getMimeType(), $allowedTypes, true)) {
            return $this->hydraError(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'Invalid file type. Allowed: jpeg, png, gif, webp.'
            );
        }

        // Validate file size (5 MB max)
        if ($file->getSize() > 5 * 1024 * 1024) {
            return $this->hydraError(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'File too large. Maximum size: 5 MB.'
            );
        }

        // Delete old avatar file if it exists
        $oldAvatar = $user->getAvatar();
        if ($oldAvatar) {
            // Seul un fichier du dossier des avatars peut être supprimé (jamais un chemin arbitraire)
            $oldPath = realpath($this->getParameter('kernel.project_dir') . '/public' . $oldAvatar);
            $avatarDir = realpath($this->getParameter('kernel.project_dir') . '/public/uploads/avatars');
            if ($oldPath && $avatarDir && str_starts_with($oldPath, $avatarDir . DIRECTORY_SEPARATOR) && is_file($oldPath)) {
                unlink($oldPath);
            }
        }

        // Generate unique filename and move file
        $filename = Uuid::v4() . '.' . $file->guessExtension();
        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/avatars';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $file->move($uploadDir, $filename);

        // Update user entity
        $avatarPath = '/uploads/avatars/' . $filename;
        $user->setAvatar($avatarPath);
        $this->entityManager->flush();

        return new JsonResponse([
            '@context' => '/api/contexts/User',
            '@type' => 'User',
            'avatar' => $avatarPath,
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
