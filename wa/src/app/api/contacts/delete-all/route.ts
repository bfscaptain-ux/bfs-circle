import { NextResponse } from 'next/server';
import { requireRole, toErrorResponse } from '@/lib/auth/account';
import { supabaseAdmin } from '@/lib/automations/admin-client';

export async function POST() {
  try {
    // Only admin or owner can delete all contacts for the account
    const ctx = await requireRole('admin');

    // 1. Count contacts to be deleted
    const { count, error: countErr } = await supabaseAdmin()
      .from('contacts')
      .select('*', { count: 'exact', head: true })
      .eq('account_id', ctx.accountId);

    if (countErr) {
      console.error('[contacts/delete-all] count error:', countErr);
      return NextResponse.json(
        { error: 'Failed to count contacts' },
        { status: 500 }
      );
    }

    const totalToDelete = count ?? 0;
    if (totalToDelete === 0) {
      return NextResponse.json({
        success: true,
        count: 0,
        message: 'No contacts found to delete',
      });
    }

    // 2. Delete all contacts belonging to this account
    const { error: delErr } = await supabaseAdmin()
      .from('contacts')
      .delete()
      .eq('account_id', ctx.accountId);

    if (delErr) {
      console.error('[contacts/delete-all] delete error:', delErr);
      return NextResponse.json(
        { error: delErr.message || 'Failed to delete contacts' },
        { status: 500 }
      );
    }

    return NextResponse.json({
      success: true,
      count: totalToDelete,
      message: `Successfully deleted ${totalToDelete} contacts`,
    });
  } catch (err) {
    return toErrorResponse(err);
  }
}
