<?php

declare(strict_types=1);

namespace SevenSpan\Chat;

use App\Models\User;
use SevenSpan\Chat\Helpers\Helper;
use SevenSpan\Chat\Models\Message;
use SevenSpan\Chat\Models\ChannelUser;
use SevenSpan\Chat\Models\MessageRead;
use SevenSpan\Chat\Models\Channel as ChannelModel;

class Channel
{
    public function list(int $userId, int $perPage = null)
    {
        $channels = ChannelModel::select('channels.id', 'name', 'channel_id', 'unread_message_count')->join('channel_users', 'channels.id', '=', 'channel_users.channel_id')->where('channel_users.user_id', $userId)->orderBy('channel_users.unread_message_count', 'DESC');
        $channels = $perPage ? $channels->paginate($perPage) : $channels->get();
        return $channels;
    }

    public function detail(int $userId, int $channelId)
    {
        $channel = ChannelModel::with('channelUser.user')->whereHas('channelUser', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })->where('id', $channelId)->first();
        return $channel;
    }

    public function create(int $userId, int $receiverId, string $channelName)
    {
        if ($userId == $receiverId) {
            $data['errors']['message'][] = "The sender and receiver should be different.";
            return $data;
        }

        $channel = ChannelModel::create(['name' => $channelName, 'created_by' => $userId]);

        ChannelUser::create(['user_id' => $userId, 'channel_id' => $channel->id, 'created_by' => $userId]);
        ChannelUser::create(['user_id' => $receiverId, 'channel_id' => $channel->id, 'created_by' => $userId]);

        $data['message'] = "Channel created successfully.";
        return $data;
    }

    public function update(int $userId, int $channelId,  $channelName)
    {
        $channel = $this->detail($userId, $channelId);

        if ($channel == null) {
            $data['errors']['message'][] = "Channel not found.";
            return $data;
        }

        $channel->update(['name' => $channelName, 'updated_by' => $userId]);
        $data['message'] = "Channel updated successfully.";
        return $data;
    }

    public function delete(int $userId, int $channelId)
    {
        $channel = $this->detail($userId, $channelId);

        if ($channel == null) {
            $data['errors']['message'][] = "Channel not found.";
            return $data;
        }

        $channel->update(['deleted_by' => $userId]);

        $this->clearMessages($userId, $channelId);

        $channel->channelUser()->delete();
        $channel->delete();

        $data['message'] = "Channel deleted successfully.";
        return $data;
    }

    public function clearMessages(int $userId, int $channelId)
    {
        $channel = $this->detail($userId, $channelId);

        if ($channel == null) {
            $data['errors']['message'][] = "Channel not found.";
            return $data;
        }

        $messages = Message::where('channel_id', $channelId)->get();
        $documents = $messages->whereNotNull('disk');
        if ($documents->isNotEmpty()) {
            foreach ($documents as $document) {
                Helper::fileDelete($document->disk, $document->path, $document->filename);
            }
        }
        MessageRead::where('channel_id', $channelId)->delete();
        Message::where('channel_id', $channelId)->delete();
        ChannelUser::where('channel_id', $channelId)->update(['unread_message_count' => 0]);

        $data['message'] = "Channel message clear successfully.";

        return $data;
    }

    public function getFiles(int $userId, int $channelId, string $type = 'image', int $perPage = null)
    {
        if (!in_array($type, ['image', 'zip'])) {
            $data['errors']['type'][] = 'The files types must be image or zip.';
            return $data;
        }
        $messages = Message::where('channel_id', $channelId)->where('type', $type)->orderBy('id', 'DESC');
        $messages = $perPage == null ? $messages->get() : $messages->paginate($perPage);
        return $messages;
    }

    public function usersList(int $userId, string $name = null, int $perPage = null)
    {
        $users = User::where('id', '!=', $userId);

        if ($name) {
            $users->where("name", 'LIKE', "{$name}%");
        }

        $users = $perPage ? $users->paginate($perPage) : $users->get();
        return $users;
    }
}