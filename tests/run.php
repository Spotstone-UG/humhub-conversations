<?php
// SPDX-License-Identifier: AGPL-3.0-only

declare(strict_types=1);

$root = dirname(__DIR__);
$required = [
    'Module.php',
    'config.php',
    'module.json',
    'migrations/m260917_150000_initial.php',
    'migrations/m260917_160000_add_message_edit_marker.php',
    'migrations/m260917_170000_add_emoji_reactions.php',
    'migrations/m260917_180000_add_message_revision_history.php',
    'migrations/m260917_190000_add_post_emoji_reactions.php',
    'models/Conversation.php',
    'models/ConversationMessage.php',
    'models/ConversationUserState.php',
    'models/ConversationReadReceipt.php',
    'models/ConversationUserSetting.php',
    'models/ConversationReaction.php',
    'models/ConversationMessageRevision.php',
    'models/PostEmojiReaction.php',
    'services/ConversationStateService.php',
    'services/ConversationReactionService.php',
    'services/EmojiPaletteService.php',
    'services/PostEmojiReactionService.php',
    'controllers/ConversationController.php',
    'controllers/PostReactionController.php',
    'widgets/ConversationForm.php',
    'widgets/views/conversationForm.php',
    'widgets/PostEmojiReactionLink.php',
    'widgets/views/postEmojiReactionLink.php',
];

foreach ($required as $file) {
    if (!is_file($root . DIRECTORY_SEPARATOR . $file)) {
        fwrite(STDERR, "Missing required file: $file\n");
        exit(1);
    }
}

$manifest = json_decode((string) file_get_contents($root . '/module.json'), true, 512, JSON_THROW_ON_ERROR);
if (($manifest['id'] ?? null) !== 'conversations' || ($manifest['humhub']['minVersion'] ?? null) !== '1.18.5') {
    fwrite(STDERR, "Manifest does not target HumHub 1.18.5.\n");
    exit(1);
}

$messageModel = (string) file_get_contents($root . '/models/ConversationMessage.php');
if (!str_contains($messageModel, 'protected $streamChannel = null')) {
    fwrite(STDERR, "Conversation messages must not become stream entries.\n");
    exit(1);
}

$module = (string) file_get_contents($root . '/Module.php');
$card = (string) file_get_contents($root . '/widgets/ConversationCard.php');
$styles = (string) file_get_contents($root . '/resources/conversations.css');
foreach (['getContentClasses', 'Conversation::class'] as $requiredToken) {
    if (!str_contains($module, $requiredToken)) {
        fwrite(STDERR, "Conversation is not registered for the standard stream composer: $requiredToken\n");
        exit(1);
    }
}
foreach (['WallStreamEntryWidget', 'ConversationForm::class', 'createFormSortOrder = 110'] as $requiredToken) {
    if (!str_contains($card, $requiredToken)) {
        fwrite(STDERR, "Conversation is not configured as an additive stream composer: $requiredToken\n");
        exit(1);
    }
}
if (!str_contains($styles, 'post%2Fpost%2Fcreate-form"] { display: block !important; }')) {
    fwrite(STDERR, "The standard Beitrag composer must remain visible.\n");
    exit(1);
}

$messageService = (string) file_get_contents($root . '/services/ConversationService.php');
$messageController = (string) file_get_contents($root . '/controllers/ConversationController.php');
foreach (['editMessage', 'created_by', 'edited_at', 'ConversationMessageRevision', 'previous_message', 'revised_message'] as $requiredToken) {
    if (!str_contains($messageService . $messageController, $requiredToken)) {
        fwrite(STDERR, "Message editing protection missing: $requiredToken\n");
        exit(1);
    }
}

$reactionService = (string) file_get_contents($root . '/services/ConversationReactionService.php');
$reactionController = (string) file_get_contents($root . '/controllers/ConversationController.php');
foreach (['toggle', 'EmojiPaletteService', 'actionReact'] as $requiredToken) {
    if (!str_contains($reactionService . $reactionController, $requiredToken)) {
        fwrite(STDERR, "Emoji reaction implementation missing: $requiredToken\n");
        exit(1);
    }
}

$postReaction = (string) file_get_contents($root . '/services/PostEmojiReactionService.php') . (string) file_get_contents($root . '/controllers/PostReactionController.php') . (string) file_get_contents($root . '/Events.php');
foreach (['PostEmojiReaction', 'EmojiPaletteService', 'onWallEntryLinksInit', 'onWallEntryLinksRun', 'removeWidget(LikeLink::class)', 'WallEntryLinks'] as $requiredToken) {
    if (!str_contains($postReaction, $requiredToken)) {
        fwrite(STDERR, "Post emoji reaction integration missing: $requiredToken\n");
        exit(1);
    }
}

$stateService = (string) file_get_contents($root . '/services/ConversationStateService.php');
foreach (['ConversationUserState', 'ConversationReadReceipt', 'readReceiptsEnabled'] as $requiredToken) {
    if (!str_contains($stateService, $requiredToken)) {
        fwrite(STDERR, "Personal state/receipt privacy rule missing: $requiredToken\n");
        exit(1);
    }
}

echo "Static Conversations module checks passed.\n";
