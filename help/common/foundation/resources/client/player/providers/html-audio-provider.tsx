import {usePlayerActions} from '@common/player/hooks/use-player-actions';
import {usePlayerStore} from '@common/player/hooks/use-player-store';
import {PlayerStoreContext} from '@common/player/player-context';
import {useHtmlMediaApi} from '@common/player/providers/html-media/use-html-media-api';
import {useHtmlMediaEvents} from '@common/player/providers/html-media/use-html-media-events';
import {useHtmlMediaInternalState} from '@common/player/providers/html-media/use-html-media-internal-state';
import {useContext, useEffect, useRef, useState} from 'react';

const urlCache = new Map<number | string, string>();

export function HtmlAudioProvider() {
  const ref = useRef<HTMLAudioElement>(null);

  const autoPlay = usePlayerStore(s => s.options.autoPlay);
  const muted = usePlayerStore(s => s.muted);
  const cuedMedia = usePlayerStore(s => s.cuedMedia);
  const blobSrcResolver = usePlayerStore(
    s => s.options.htmlAudioBlob?.srcResolver,
  );
  const store = useContext(PlayerStoreContext);
  const {emit} = usePlayerActions();

  const state = useHtmlMediaInternalState(ref);
  const events = useHtmlMediaEvents(state);
  const providerApi = useHtmlMediaApi(state);

  useEffect(() => {
    store.setState({
      providerApi,
    });
  }, [store, providerApi]);

  const [src, setSrc] = useState<string | undefined>(() => {
    if (!cuedMedia?.src || cuedMedia?.provider === 'htmlAudioBlob') {
      return undefined;
    }

    return cuedMedia.initialTime
      ? `${cuedMedia.src}#t=${cuedMedia.initialTime}`
      : cuedMedia.src;
  });

  useEffect(() => {
    if (
      cuedMedia &&
      cuedMedia.provider === 'htmlAudioBlob' &&
      blobSrcResolver
    ) {
      if (urlCache.has(cuedMedia.id)) {
        setSrc(urlCache.get(cuedMedia.id));
        return;
      }

      emit('buffering', {isBuffering: true});
      blobSrcResolver(cuedMedia)
        .then(blob => {
          if (blob) {
            const url = URL.createObjectURL(blob);
            urlCache.set(cuedMedia.id, url);
            setSrc(url);
          }
        })
        .finally(() => {
          emit('buffering', {isBuffering: false});
        });
    }
  }, [cuedMedia]);

  useEffect(() => {
    return () => {
      if (src && cuedMedia?.provider === 'htmlAudioBlob') {
        URL.revokeObjectURL(src);
        urlCache.delete(cuedMedia.id);
      }
    };
  }, [src]);

  return (
    <audio
      className="h-full w-full"
      ref={ref}
      src={src}
      autoPlay={autoPlay}
      muted={muted}
      {...events}
    />
  );
}
