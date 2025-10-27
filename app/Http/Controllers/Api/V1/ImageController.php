<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\UploadException;
use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Models\User;
use App\Services\ImageService;
use App\Services\UserService;
use App\Utils;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ImageController extends Controller
{
    /**
     * @throws AuthenticationException
     */
    public function upload(Request $request, ImageService $service): Response
    {
        if ($request->hasHeader('Authorization')) {
            $guards = array_keys(config('auth.guards'));

            if (empty($guards)) {
                $guards = [null];
            }

            foreach ($guards as $guard) {
                if (Auth::guard($guard)->check()) {
                    Auth::shouldUse($guard);
                    break;
                }
            }

            if (! Auth::check()) {
                throw new AuthenticationException('Authentication failed.');
            }
        }

        try {
            $image = $service->store($request);
        } catch (UploadException $e) {
            return $this->fail($e->getMessage());
        } catch (\Throwable $e) {
            Utils::e($e, 'Api 上传文件时发生异常');
            if (config('app.debug')) {
                return $this->fail($e->getMessage());
            }
            return $this->fail('服务异常，请稍后再试');
        }
        return $this->success('上传成功', $image->setAppends(['pathname', 'links'])->only(
            'key', 'name', 'pathname', 'origin_name', 'size', 'mimetype', 'extension', 'md5', 'sha1', 'links'
        ));
    }

    public function images(Request $request): Response
    {
        /** @var User $user */
        $user = Auth::user();

        $images = $user->images()->filter($request)->paginate(40)->withQueryString();
        $images->getCollection()->each(function (Image $image) {
            $image->human_date = $image->created_at->diffForHumans();
            $image->date = $image->created_at->format('Y-m-d H:i:s');
            $image->append(['pathname', 'links'])->setVisible([
                'album', 'key', 'name', 'pathname', 'origin_name', 'size', 'mimetype', 'extension', 'md5', 'sha1',
                'width', 'height', 'links', 'human_date', 'date',
            ]);
        });
        return $this->success('success', $images);
    }

    public function movement(Request $request): Response
    {
        /** @var User $user */
        $user = Auth::user();
        DB::transaction(function () use ($user, $request) {
            /** @var null|Album $album */
            $album = $user->albums()->find((int) $request->album_id);
            $user->images()->where('key', $request->image_key)->update([
                'album_id' => $album->id ?? null,
            ]);
            if ($album) {
                $album->image_num = $album->images()->count();
                $album->save();
            }
            if ($albumId = (int) $request->album_id) {
                /** @var Album $originAlbum */
                $originAlbum = $user->albums()->find($albumId);
                $originAlbum->image_num = $originAlbum->images()->count();
                $originAlbum->save();
            }
        });
        return $this->success('移动成功');
    }

    // 获取图片
    public function get(Request $request): Response
    {
        /** @var User $user */
        $user = Auth::user();
        $image = $user->images()->where('key', $request->route('key'))->firstOrFail();
        $image->append(['pathname', 'links'])->setVisible([
                'album', 'key', 'name', 'pathname', 'origin_name', 'size', 'mimetype', 'extension', 'md5', 'sha1',
                'width', 'height', 'links', 'human_date', 'date',
            ]);
        return $this->success('success', $image);
    }
    
    public function destroy(Request $request): Response
    {
        /** @var User $user */
        $user = Auth::user();
        (new UserService())->deleteImages([$request->route('key')], $user, 'key');
        return $this->success('删除成功');
    }

    // 当前用户是否已存在图片
    public function is_exist(Request $request): Response
    {
        /** @var User $user */
        $user = Auth::user();

        $image = Image::query()
                ->where('user_id', $user->id)
                ->where('md5', $request->md5)
                ->where('sha1', $request->sha1)
                ->firstOr(fn() => abort(404));

        return $this->success('查询图片存在', $image->setAppends(['pathname', 'links'])->only(
            'key', 'name', 'pathname', 'origin_name', 'size', 'mimetype', 'extension', 'md5', 'sha1', 'links'
        ));
    }
}
