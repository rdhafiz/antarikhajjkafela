import { http } from '@admin/lib/http';
import { useConfirm } from '@admin/composables/useConfirm';

/**
 * Before deleting an image: if it is still used somewhere, say where and stop
 * (the server refuses too). Resolves true when the image is free to delete.
 */
export async function mediaIsFree(id: number): Promise<boolean> {
    const { data } = await http.get<{ data: string[] }>(`/media/${id}/used-in`);
    if (!data.data.length) return true;

    await useConfirm().confirm({
        title: 'This image is still in use',
        message: 'Replace it in these places first, then delete it. Deleting it now would leave a broken image there.',
        items: data.data,
        notice: true,
        tone: 'primary',
    });

    return false;
}
