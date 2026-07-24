package dev.pam.mobileui

import android.content.Context
import android.view.View
import dev.pam.nativeapp.protocol.WireValue
import dev.pam.nativeapp.views.NativeViewEmitter
import dev.pam.nativeapp.views.NativeViewFactoryV2

class MobileUiHostFactory(
    @Suppress("UNUSED_PARAMETER") context: Context,
) : NativeViewFactoryV2 {
    override fun create(
        context: Context,
        emitter: NativeViewEmitter,
    ): View = MobileUiHost(context, emitter)

    override fun update(
        view: View,
        properties: Map<String, WireValue>,
    ) {
        require(view is MobileUiHost) { "pam.mobile_ui.host requires MobileUiHost" }
        view.update(properties)
    }

    override fun release(view: View) {
        (view as? MobileUiHost)?.release()
    }
}
