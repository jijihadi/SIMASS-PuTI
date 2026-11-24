<?php

namespace App\Enums;

enum RealtimeChannel: string
{
    case NocTickets = 'noc.tickets';
    case TicketPublic = 'ticket.{ticketId}';
    case TicketInternal = 'ticket.{ticketId}.internal';
    case TicketPresence = 'ticket.{ticketId}.presence';
    case UserPersonal = 'user.{userId}';
    case PresenceLive = 'presence.live';
    case InventoryLive = 'inventory.live';
    case AdminAudit = 'admin.audit';

    public static function ticketPublic(int $ticketId): string
    {
        return "ticket.{$ticketId}";
    }

    public static function ticketInternal(int $ticketId): string
    {
        return "ticket.{$ticketId}.internal";
    }

    public static function ticketPresence(int $ticketId): string
    {
        return "ticket.{$ticketId}.presence";
    }

    public static function userPersonal(int $userId): string
    {
        return "user.{$userId}";
    }
}
